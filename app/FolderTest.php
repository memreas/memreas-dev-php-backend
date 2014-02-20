<?php
use Zend\Session\Container;
use PHPImageWorkshop\ImageWorkshop;

class FolderTest {

    public function __construct() {
    }
    
    private function rmWorkDir($dir) {
		$it = new \RecursiveDirectoryIterator($dir);
		$files = new \RecursiveIteratorIterator($it,
					 \RecursiveIteratorIterator::CHILD_FIRST);
		foreach($files as $file) {
			if ($file->getFilename() === '.' || $file->getFilename() === '..') {
				continue;
			}
			if ($file->isDir()){
				rmdir($file->getRealPath());
			} else {
				unlink($file->getRealPath());
			}
		}
		rmdir($dir);
	}
	
	public function exec() {
	
		try {
				//Make directories here - create a unique directory by user_id
				$temp_job_uuid_dir = uniqid();
error_log("temp_job_uuid_dir ----> $temp_job_uuid_dir" . PHP_EOL);

				//Some Settings
				//$WebHome 				= 'data/'; // 2944444a-cc8f-11e2-8fd6-12313909a953 in JSON
				$WebHome 				= '/memreas_transcode_worker/'; // 2944444a-cc8f-11e2-8fd6-12313909a953 in JSON
				$HomeDirectory			= $WebHome . $temp_job_uuid_dir . '/'; //Home Directory ends with / (slash) :::: Your AMAZON home
	
				$DestinationDirectory	= 'media/'; //Upload Directory ends with / (slash):::: media/ in JSON
				$ConvertedDirectory		= 'media/'; //Converted Directory ends with / (slash) :::: media/ in JSON
				$p1080 					= '1080p/'; // Your 1080p Dir, end with slash (/)
				$thumbnails				= 'thumbnails/';  // Your thumbnails Dir, end with slash (/)
				$hls 					= 'hls/';  // Your hls Dir, end with slash (/)
				$web					= 'web/';  // Your web Dir, end with slash (/)
				$x264					= 'x264/';  // Your x264 Dir, end with slash (/)
				$_79x80					= '79x80/';  // Your 79x80 Dir, end with slash (/)
				$_448x306				= '448x306/';  // Your 448x306 Dir, end with slash (/)
				$_384x216				= '384x216/';  // Your _384x216 Dir, end with slash (/)
				$_98x78					= '98x78/';  // Your 98x78 Dir, end with slash (/)
				$identifier				= '2944444a-cc8f-11e2-8fd6-12313909a953_354614555375243'; // Change accordingly

				//Thumbnail settings	
				$tnWidth 				= 448;
				$tnHeight 				= 306;
				$tnfreqency 			= 60; // in seconds - 60 means every 60 seconds (minute)
				$errstr = '';

				//Make directories here - create a unique directory by user_id
				$toCreate = array(
					$HomeDirectory, // data/temp_uuid_dir/
					$HomeDirectory.$DestinationDirectory, // data/temp_job_uuid_dir/media/
					$HomeDirectory.$DestinationDirectory.$thumbnails, // data/temp_job_uuid_dir/media/thumbnails/
					$HomeDirectory.$DestinationDirectory.$thumbnails.$_79x80, // data/temp_job_uuid_dir/media/thumbnails/79x80/
					$HomeDirectory.$DestinationDirectory.$thumbnails.$_448x306, // data/temp_job_uuid_dir/media/thumbnails/448x306/
					$HomeDirectory.$DestinationDirectory.$thumbnails.$_384x216, // data/temp_job_uuid_dir/media/thumbnails/384x216/
					$HomeDirectory.$DestinationDirectory.$thumbnails.$_98x78, // data/temp_job_uuid_dir/media/thumbnails/98x78/
					$HomeDirectory.$DestinationDirectory.$x264, // data/temp_job_uuid_dir/media/x264/
					$HomeDirectory.$DestinationDirectory.$web, // data/temp_job_uuid_dir/media/web/
					$HomeDirectory.$DestinationDirectory.$hls, // data/temp_job_uuid_dir/media/hls/
					$HomeDirectory.$DestinationDirectory.$p1080, // data/temp_job_uuid_dir/media/p1080/
				);

$result = shell_exec ( "whoami" ); 
echo "result ---> $result\n";
				$permissions = 0777;
				foreach ($toCreate as $dir) {
				  	//mkdir($dir, $permissions, TRUE);
				  	
				  	$save = umask(0);
				  	if (mkdir($dir)) chmod($dir, $permissions);
				  	umask($save);
				  	
					//$cmd = "mkdir ".$dir;
				  	//$result = shell_exec($cmd);
					//error_log("mkdir $dir result ---> ".$result.PHP_EOL);
					//$cmd = "chmod ug+x ".$dir;
				  	//$result = shell_exec($cmd);
					//error_log("chmod ug+x $dir result ---> ".$result.PHP_EOL);
$cmd = "ls -al ".$dir.'..';
$result = shell_exec ( $cmd );
echo "cmd ---> $cmd\n";
echo "result ---> $result\n";
				}				
				
		} catch (Exception $e) {
			error_log( 'Caught exception: '.  $e->getMessage() . PHP_EOL);
			echo 'Caught exception: ',  $e->getMessage(), "\n";
			//Always delete the temp dir...
			$result = $this->rmWorkDir($HomeDirectory);
		}

	} // End exec()
}

//Create the object and execute
$folderTest = new FolderTest();
$folderTest->exec();

?>
