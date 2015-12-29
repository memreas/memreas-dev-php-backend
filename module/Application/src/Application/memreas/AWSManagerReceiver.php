<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\memreas;

use Aws\S3\MultipartUploader;
use Aws\Exception\MultipartUploadException;
use PHPImageWorkshop\ImageWorkshop;
use Application\Model\MemreasConstants;
use Application\memreas\Mlog;

class AWSManagerReceiver {
	public $aws = null;
	public $s3 = null;
	public $ses = null;
	public $service_locator = null;
	public $dbAdapter = null;
	public $temp_job_uuid = null;
	public $memreasTranscoder = null;
	public function __construct($service_locator) {
		try {
			$this->service_locator = $service_locator;
			
			$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
			
			// Fetch aws handle
			$this->aws = MemreasConstants::fetchAWS ();
			
			// Fetch the S3 class
			$this->s3 = $this->aws->createS3 ();
			
			// Fetch the Ses class
			$this->ses = $this->aws->createSes ();
			
			$this->memreasTranscoder = new MemreasTranscoder ( $this, $this->service_locator );
		} catch ( Exception $e ) {
			Mlog::addone ( __FILE__ . __METHOD__ . 'Caught exception: ', $e->getMessage () );
			throw $e;
		}
	}
	function fetchBackLogEntry($server_name) {
		//
		// Method fetches next 
		//
		try {
			//
			// Check high priority first
			//
			$query_string = "SELECT tt FROM " . 
			" Application\Entity\TranscodeTransaction tt " . 
			" where tt.transcode_status='backlog' " . 
			" and tt.priority='high' " . 
			" and tt.server_lock is null " . 
			" order by tt.transcode_start_time asc";
			$query = $this->dbAdapter->createQuery ( $query_string );
			$result = $query->getArrayResult ();
			if ($result) {
				foreach ( $result as $entry ) {
					$message_data = json_decode ( $entry ['message_data'], true );
					$message_data ['transcode_transaction_id'] = $entry ['transcode_transaction_id'];
					$message_data ['backlog'] = 1;
					$message_data ['server_lock'] = $server_name;
					break;
				}
				Mlog::addone(__CLASS__.__METHOD__.__LINE__.":: high query message_data::", $message_data);
				sleep(5);
				return $message_data;
			} else{
				Mlog::addone(__CLASS__.__METHOD__.__LINE__.":: else high query message_data::", $message_data);
				sleep(5);
			}
			
			//
			// Check medium priority next
			//
			$query_string = "SELECT tt FROM " . 
			" Application\Entity\TranscodeTransaction tt " . 
			" where tt.transcode_status='backlog' " . 
			" and tt.priority='medium' " . 
			" and tt.server_lock is null " . 
			" order by tt.transcode_start_time asc";
			$query = $this->dbAdapter->createQuery ( $query_string );
			$result = $query->getArrayResult ();
			if ($result) {
				foreach ( $result as $entry ) {
					$message_data = json_decode ( $entry ['message_data'], true );
					$message_data ['transcode_transaction_id'] = $entry ['transcode_transaction_id'];
					$message_data ['backlog'] = 1;
					$message_data ['server_lock'] = $server_name;
					break;
				}
				Mlog::addone(__CLASS__.__METHOD__.__LINE__.":: medium query message_data::", $message_data);
				sleep(5);
				return $message_data;
			} else{
				Mlog::addone(__CLASS__.__METHOD__.__LINE__.":: else medium query message_data::", $message_data);
				sleep(5);
			}
			
			//
			// Check low priority last
			//
			$query_string = "SELECT tt FROM " .
					" Application\Entity\TranscodeTransaction tt " .
					" where tt.transcode_status='backlog' " .
					" and tt.priority='low' " .
					" and tt.server_lock is null " .
					" order by tt.transcode_start_time asc";
			$query = $this->dbAdapter->createQuery ( $query_string );
			$result = $query->getArrayResult ();
			if ($result) {
				foreach ( $result as $entry ) {
					$message_data = json_decode ( $entry ['message_data'], true );
					$message_data ['transcode_transaction_id'] = $entry ['transcode_transaction_id'];
					$message_data ['backlog'] = 1;
					$message_data ['server_lock'] = $server_name;
					break;
				}
				Mlog::addone(__CLASS__.__METHOD__.__LINE__.":: low query message_data::", $message_data);
				sleep(5);
				return $message_data;
			} else{
				Mlog::addone(__CLASS__.__METHOD__.__LINE__.":: low medium query message_data::", $message_data);
				sleep(5);
			}
			
			//
			// If nothing found retur null ... slow day :)
			//
			return null;
				
			
		} catch ( Exception $e ) {
			Mlog::addone ( __CLASS__ . __METHOD__ . '::Caught exception', $e->getMessage () );
			throw $e;
		}
	}
	function snsProcessMediaSubscribe($message_data) {
		try {
			$result = $this->memreasTranscoder->exec ( $message_data, false );
			return $result;
		} catch ( Exception $e ) {
			Mlog::addone ( __FILE__ . __METHOD__ . __LINE__ . 'Caught exception: ', $e->getMessage () );
			// Remove the work directory
			// $dir = getcwd() . MemreasConstants::DATA_PATH .
			// $this->temp_job_uuid;
			// $dirRemoved = new RmWorkDir($dir);
			// return false;
			throw $e;
		}
	}
	function sesEmailErrorToAdmin($msg) {
		// Mlog::addone ( __CLASS__ . __METHOD__ . '::About to send email::',
		// $msg );
		try {
			$result = $this->ses->sendEmail ( array (
					// Source is required
					'Source' => 'admin@memreas.com',
					// Destination is required
					'Destination' => array (
							'ToAddresses' => array (
									'admin@memreas.com' 
							) 
					),
					// Message is required
					'Message' => array (
							// Subject is required
							'Subject' => array (
									// Data is required
									'Data' => 'memreasdev-bew error',
									'Charset' => 'UTF-8' 
							),
							// Body is required
							'Body' => array (
									'Text' => array (
											// Data is required
											'Data' => $msg,
											'Charset' => 'UTF-8' 
									) 
							) 
					),
					'ReplyToAddresses' => array (
							'admin@memreas.com' 
					),
					'ReturnPath' => 'admin@memreas.com' 
			) );
			if ($result) {
				Mlog::addone ( __FILE__ . __METHOD__ . '::email sent::$msg', $msg );
			} else {
				Mlog::addone ( __FILE__ . __METHOD__ . '::email not sent::$msg', $msg );
			}
		} catch ( \Exception $e ) {
			Mlog::addone ( __CLASS__ . __METHOD__ . "::line::" . $e->getLine () . '::Caught exception: ', $e->getMessage () );
			throw $e;
		}
	}
	function pullMediaFromS3($s3file, $file) {
		try {
			Mlog::addone ( __FILE__ . __METHOD__ . '::pulling s3file', $s3file );
			$result = $this->s3->getObject ( array (
					'Bucket' => MemreasConstants::S3BUCKET,
					'Key' => $s3file,
					'SaveAs' => $file 
			) );
			$lsal = shell_exec ( 'ls -al ' . $file );
			Mlog::addone ( __FILE__ . __METHOD__ . '::finished pullMediaFromS3', $lsal );
			return true;
		} catch ( Exception $e ) {
			throw $e;
		}
	}
	function pushThumbnailsToS3($dir, $s3path) {
		try {
			$keyPrefix = $s3path;
			$options = array (
					// 'params' => array('ACL' => 'public-read'),
					'concurrency' => 20,
					'ServerSideEncryption' => 'AES256',
					'StorageClass' => 'REDUCED_REDUNDANCY' 
			);
			
			$result = $this->s3->uploadDirectory ( $dir, MemreasConstants::S3BUCKET, $keyPrefix, $options );
		} catch ( Exception $e ) {
			throw $e;
		}
	}
	function copyMediaInS3($bucket, $target, $source) {
		try {
			$result = $this->s3->copyObject ( array (
					'Bucket' => $bucket,
					'Key' => $target,
					// 'CopySource' => "{".$bucket."}/{".$source."}",
					'CopySource' => $bucket . '/' . $source,
					'ServerSideEncryption' => 'AES256',
					'StorageClass' => 'REDUCED_REDUNDANCY' 
			) );
			return $result;
		} catch ( Exception $e ) {
			throw $e;
		}
	}
	function pushMediaToS3($file, $s3file, $content_type, $isVideo = false, $bucket = MemreasConstants::S3BUCKET, $encyption = true) {
		try {
			// Use default bucket
			/*
			 * Uploader - section
			 */
			$result = 0;
			$file_size = filesize ( $file );
			if ($file_size < MemreasConstants::SIZE_5MB) {
				// Upload a file.
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . "::pushMediaToS3 filesize < 5MB ::", $file_size );
				if ($encryption) {
					$result = $this->s3->putObject ( array (
							'Bucket' => $bucket,
							'Key' => $s3file,
							'SourceFile' => $file,
							'ContentType' => $content_type,
							// 'ACL' => 'public-read',
							'ServerSideEncryption' => 'AES256',
							'StorageClass' => 'REDUCED_REDUNDANCY' 
					) );
				} else {
					$result = $this->s3->putObject ( array (
							'Bucket' => $bucket,
							'Key' => $s3file,
							'SourceFile' => $file,
							'ContentType' => $content_type,
							// 'ACL' => 'public-read',
							'StorageClass' => 'REDUCED_REDUNDANCY' 
					) );
				}
			} else {
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . "::pushMediaToS3 filesize > 5MB ::", $file_size );
				if ($encyption) {
					$uploader = new MultipartUploader ( $this->s3, $file, [ 
							'bucket' => $bucket,
							'key' => $s3file,
							'Content-Type' => $content_type,
							'ServerSideEncryption' => 'AES256',
							'StorageClass' => 'REDUCED_REDUNDANCY' 
					] );
				} else {
					$uploader = new MultipartUploader ( $this->s3, $file, [ 
							'bucket' => $bucket,
							'key' => $s3file,
							'Content-Type' => $content_type,
							'StorageClass' => 'REDUCED_REDUNDANCY' 
					] );
				}
				
				try {
					$result = $uploader->upload ();
					// echo "Upload complete: {$result['ObjectURL'}\n";
					Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . "::MultiPartUpload worked::", $result );
				} catch ( MultipartUploadException $e ) {
					Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . "::MultiPartUploadException::", $e->getMessage () );
				}
			}
			
			return $result;
		} catch ( Exception $e ) {
			throw $e;
		}
	}
	function fetchResizeUpload($message_data, $job_dir, $s3file, $s3output_path, $height, $width) {
		try {
			/*
			 * Fetch image and create thumbnails
			 */
			$user_id = $message_data ['user_id'];
			$media_id = $message_data ['media_id'];
			$content_type = $message_data ['content_type'];
			$s3path = $message_data ['s3path'];
			$s3file_name = $message_data ['s3file_name'];
			
			/*
			 * Local server data
			 */
			$dirPath = getcwd () . "/data/" . $user_id . "/media/";
			$splitter = explode ( "thumbnail/", $s3file );
			$thumbnail_name = $splitter [1];
			$splitter = explode ( $thumbnail_name, $s3file );
			$path = $splitter [0];
			$thumbnail_file = $path . $height . "x" . $width . "/" . $thumbnail_name;
			
			$file = $job_dir . $thumbnail_name;
			$result = $this->s3->getObject ( array (
					'Bucket' => MemreasConstants::S3BUCKET,
					'Key' => $s3file,
					'SaveAs' => $file 
			) );
			
			/*
			 * Resize images - section
			 */
			$layer = ImageWorkshop::initFromPath ( $file );
			// $layer->resizeInPixel($height, $width, true, 0, 0, 'MM');
			// //Maintains
			// image
			$layer->resizeInPixel ( $height, $width );
			$dirPath = getcwd () . "/data/" . $user_id . "/media/" . $height . "x" . $width . "/";
			$job_sub_dir = $job_dir . $height . "x" . $width . "/";
			if (! file_exists ( $job_sub_dir )) {
				$oldumask = umask ( 0 );
				mkdir ( $job_sub_dir, 01777, true );
				umask ( $oldumask );
			}
			
			$createFolders = true;
			$backgroundColor = null; // transparent, only for PNG (otherwise it
			                         // will
			                         // be white if set null)
			$imageQuality = 95; // useless for GIF, usefull for PNG and JPEG (0
			                    // to
			                    // 100%)
			$layer->save ( $job_sub_dir, $thumbnail_name, $createFolders, $backgroundColor, $imageQuality );
			$file = $job_sub_dir . $thumbnail_name;
			
			$result = 0;
			$file_size = filesize ( $file );
			if ($file_size < MemreasConstants::SIZE_5MB) {
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . "::pushMediaToS3 filesize < 5MB ::", $file_size );
				// Upload a file.
				$result = $this->s3->putObject ( array (
						'Bucket' => MemreasConstants::S3BUCKET,
						'Key' => $thumbnail_file,
						'SourceFile' => $file,
						'ContentType' => $content_type,
						'ServerSideEncryption' => 'AES256',
						'StorageClass' => 'REDUCED_REDUNDANCY' 
				) );
			} else {
				$uploader = new MultipartUploader ( $this->s3, $file, [ 
						'Bucket' => MemreasConstants::S3BUCKET,
						'Key' => $thumbnail_file,
						'SourceFile' => $file,
						'ContentType' => $content_type,
						'ServerSideEncryption' => 'AES256',
						'StorageClass' => 'REDUCED_REDUNDANCY' 
				] );
				
				try {
					$result = $uploader->upload ();
					// echo "Upload complete: {$result['ObjectURL'}\n";
					Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . "::MultiPartUpload worked::", $result );
				} catch ( MultipartUploadException $e ) {
					Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . "::MultiPartUploadException::", $e->getMessage () );
				}
			}
			
			return $thumbnail_file;
		} catch ( Exception $e ) {
			throw $e;
		}
	}
}//END



