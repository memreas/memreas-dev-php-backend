<?php

	function probe() {
		$ffprobe = '/usr/local/bin/ffprobe';
		$file = '/var/app/ephemeral0/6fb418ef-c984-42d2-92c8-3985635418b2/media/IMG_0346.MOV';
		$cmd = $ffprobe . ' -v error  -print_format json -show_format -show_streams ' . $file;
		$result = shell_exec ( $cmd );
		out(__CLASS__ . __METHOD__);
		out($cmd);
		out($result);
		//var_dump($result);
	}
	
	function out($string, $error_log=false) {
		if ($error_log) {
			error_log($string . PHP_EOL);
		} else {
			echo $string . '<br>';
		}
	}
	
	probe();
?>