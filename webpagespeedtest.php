<?php 
/**
 * Copyright (c) 2018 Grzegorz Klimczuk
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is furnished
 * to do so, subject to the following conditions:

 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.

 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

class WebPageSpeedTest { 
	public $page_a = '';
	public $page_b = '';
	public $format = '';
	private $newline = PHP_EOL;
	private $connection = null;
	
	private $page_a_time;
	private $page_b_time;
	
	function __construct($conn, $page_a, $page_b, $format) {
		$this->page_a = $page_a;
		//echo gettype($page_b);
		$this->page_b = $page_b;
        $this->format = $format;
		$this->connection = $conn;
		
		if($this->format == 'apache2handler') {
			$this->newline = '<br>';
		}
    }
	
    public function Run() { 
		if(gettype($this->page_a) != 'string') {
			echo "Error: Variable 'page_a' must be string $this->newline";
			return;
		}
		
		$this->page_a_time = $this->MeasureTimePage($this->page_a);
		if(gettype($this->page_b) == 'array') {
			$this->page_b_time = array();
			for ($i = 0; $i < count($this->page_b); $i++) {
				$this->page_b_time[$i] = $this->MeasureTimePage($this->page_b[$i]);
				$this->Compare($this->page_a, $this->page_a_time, $this->page_b[$i]);
				$this->Save($this->page_a, $this->page_a_time, $this->page_b[$i], $this->page_b_time[$i]);
			} 
		}
		elseif(gettype($this->page_b) == 'string') { 
			$this->page_b_time = $this->MeasureTimePage($this->page_b);
			$this->Compare($this->page_a, $this->page_a_time, $this->page_b);
			$this->Save($this->page_a, $this->page_a_time, $this->page_b, $this->page_b_time);
		}
		else {
			echo "Error: Variable 'page_b' must be string or array $this->newline";
		}
    } 
	
	private function MeasureTimePage($name) {
		$time_start = microtime(true);
		file_get_contents($name);
		return microtime(true) - $time_start;
	}
	
	private function GetLastMeasurePage($page_a, $page_b) {
		$sql = "SELECT * FROM `webpage_speed_test` WHERE page_a_name = '$page_a' and page_b_name = '$page_b' ORDER BY id DESC LIMIT 1";
		try {
			$result = $this->connection->query($sql);
			if($result) {
				$result = $result->fetch_assoc();
				$old_page_a_speed = $result['page_a_speed'];
				return $old_page_a_speed;
			}
		}
		catch(PDOException $e)
		{
			echo "Error: GetLastMeasurePage... $this->newline";
		}
		return 0;
	}
	
	private function Save($page_a, $page_a_time, $page_b, $page_b_time) {
		$sql = "INSERT INTO `webpage_speed_test` (page_a_name, page_a_speed, page_b_name, page_b_speed) VALUES 
		('$page_a', $page_a_time, '$page_b', $page_b_time);";
		try {
			$result = $this->connection->query($sql);
		}
		catch(PDOException $e)
		{
			echo "Error: " . $e->getMessage(). $this->newline;
		}
	}
	
	// Compare last result with current
	public function Compare($page_a, $page_a_time, $page_b) {
		$last_measure = $this->GetLastMeasurePage($page_a, $page_b);
		if($last_measure != null) {
			$compare = ($page_a_time-$last_measure)/$last_measure;
			if($compare <= -0.1) {
				$this->SendMail();
			} 
			elseif($compare <= -1.0) {
				$this->SendSMS();
			}
		}
	}
	
	public function SendMail() {
		echo "Send mail";
	}
	
	public function SendSMS() {
		echo "Send SMS";
	}
	
	public function Install() {
		echo "Installation! $this->newline";
		$result = $this->connection->query("DESCRIBE `webpage_speed_test`");
		if(!$result or count($result->fetch_assoc()) < 0){
			$sql = "
				CREATE TABLE `webpage_speed_test` (
				  `id` int(11) NOT NULL AUTO_INCREMENT,
				  `page_a_name` varchar(150) NOT NULL,
				  `page_a_speed` float NOT NULL,
				  `page_b_name` varchar(150) NOT NULL,
				  `page_b_speed` float NOT NULL,
				  PRIMARY KEY (ID)
				)";
			$this->connection->query($sql);
			echo "Installation Done! $this->newline";
		}
		else {
			echo "Installation NO-NEED! $this->newline";
		}
		echo "-------------------------------------------" . $this->newline;
	}
	
	public function Show($type) {
		if($type == 'cli') {
			return $this->ShowCLI();
		}
		elseif($type == 'html' || $type == 'apache2handler') {
			return $this->ShowHTML();
		}
		elseif($type == 'json') {
			return $this->ShowJSON();
		}
	}
	
	public function ShowCLI() {
		$result = '';
		if(gettype($this->page_b) == 'array') {
			for ($i = 0; $i < count($this->page_b); $i++) {
				$diff = $this->page_a_time - $this->page_b_time[$i];
				if($diff <= 0) {
					$diff = "" . $diff . " " . $this->page_a . " it's faster";
				}
				else {
					$diff = "" . $diff . " " . $this->page_a . " it's slower";
				}
				$result .= 'web: ' . $this->page_a . ' loaded: ' . $this->page_a_time . 'ms vs web: ' . $this->page_b[$i] . ' loaded: ' .$this->page_b_time[$i] . 'ms ' . $diff . PHP_EOL;
			} 
		}
		elseif(gettype($this->page_b) == 'string') { 
			$diff = $this->page_a_time - $this->page_b_time;
			if($diff <= 0) {
				$diff = "" . $diff . " " . $this->page_a . " it's faster";
			}
			else {
				$diff = "" . $diff . " " . $this->page_a . " it's slower";
			}
			$result = 'web: ' . $this->page_a . ' loaded: ' . $this->page_a_time . 'ms vs web: ' . $this->page_b . ' loaded: ' .$this->page_b_time . 'ms difference ' . $diff . PHP_EOL;
		}
		return $result;
	}
	
	public function ShowHTML() {
		$result = '';
		if(gettype($this->page_b) == 'array') {
			for ($i = 0; $i < count($this->page_b); $i++) {
				$diff = $this->page_a_time - $this->page_b_time[$i];
				if($diff <= 0) {
					$diff = "" . $diff . " " . $this->page_a . " it's faster";
				}
				else {
					$diff = "" . $diff . " " . $this->page_a . " it's slower";
				}
				$result .= 'web: ' . $this->page_a . ' loaded: ' . $this->page_a_time . 'ms vs web: ' . $this->page_b[$i] . ' loaded: ' .$this->page_b_time[$i] . 'ms ' . $diff . '<br>';
			} 
		}
		elseif(gettype($this->page_b) == 'string') { 
			$diff = $this->page_a_time - $this->page_b_time;
			if($diff <= 0) {
				$diff = "" . $diff . " " . $this->page_a . " it's faster";
			}
			else {
				$diff = "" . $diff . " " . $this->page_a . " it's slower";
			}
			$result = 'web: ' . $this->page_a . ' loaded: ' . $this->page_a_time . 'ms vs web: ' . $this->page_b . ' loaded: ' .$this->page_b_time . 'ms difference ' . $diff .'<br>';
		}
		return $result;
	}
	
	public function ShowJSON() {
		$result = '{ "result": [';
		if(gettype($this->page_b) == 'array') {
			for ($i = 0; $i < count($this->page_b); $i++) {
				$diff = $this->page_a_time - $this->page_b_time[$i];
				if($diff <= 0) {
					$diff = "" . $diff . " " . $this->page_a . " it's faster";
				}
				else {
					$diff = "" . $diff . " " . $this->page_a . " it's slower";
				}
				$result .= '["' . $this->page_a . '", '. $this->page_a_time . ',"' . $this->page_b[$i] . '", ' .$this->page_b_time[$i] . ',"' . $diff . '"]';
				if($i < count($this->page_b) - 1) {
					$result .= ',';
				}
			} 
		}
		elseif(gettype($this->page_b) == 'string') { 
			$diff = $this->page_a_time - $this->page_b_time;
			if($diff <= 0) {
				$diff = "" . $diff . " " . $this->page_a . " it's faster";
			}
			else {
				$diff = "" . $diff . " " . $this->page_a . " it's slower";
			}
			$result .= '["' . $this->page_a . '", '. $this->page_a_time . ',"' . $this->page_b . '", ' .$this->page_b_time . ',"' . $diff . '"]';
		}
		$result .=  ']}';
		return $result;
	}
} 

// ------------- MAIN RUN
$conn = new mysqli('localhost', 'username', 'password', 'db');
if ($conn->connect_error) {
	die("Connection failed: " . $conn->connect_error);
} 

// ONE PAGE VS ONE PAGE

$webspeed = new WebPageSpeedTest($conn, 'https://aclari.pl/', 'https://yes.pl/', PHP_SAPI); 
$webspeed->Install();
$webspeed->Run();

if(PHP_SAPI == 'cli') {
	echo $webspeed->Show('cli');
} else {
	if (isset($_GET['format'])) {
		echo $webspeed->Show(strtolower($_GET['format']));
	}
	else {
		echo $webspeed->Show('html');
	}
}

// ONE PAGE VS PAGES

$webspeed = new WebPageSpeedTest($conn, 'https://aclari.pl/', array('https://yes.pl/', 'https://yes.pl/', 'https://aclari.pl/'), PHP_SAPI); 
$webspeed->Install();
$webspeed->Run();

if(PHP_SAPI == 'cli') {
	echo $webspeed->Show('cli');
} else {
	if (isset($_GET['format'])) {
		echo $webspeed->Show(strtolower($_GET['format']));
	}
	else {
		echo $webspeed->Show('html');
	}
}

?> 