<?php
 chdir(dirname(__DIR__));

// Setup autoloading
// require 'init_autoloader.php';

function rsa_sha1_sign($policy, $private_key_filename) {
    $signature = "";

    // load the private key
    $fp = fopen($private_key_filename, "r");
    $priv_key = fread($fp, 8192);
    fclose($fp);
    $pkeyid = openssl_get_privatekey($priv_key);

    // compute signature
    openssl_sign($policy, $signature, $pkeyid);

    // free the key from memory
    openssl_free_key($pkeyid);

    return $signature;
}

function url_safe_base64_encode($value) {
    $encoded = base64_encode($value);
    // replace unsafe characters +, = and / with the safe characters -, _ and ~
    return str_replace(
        array('+', '=', '/'),
        array('-', '_', '~'),
        $encoded);
}

function create_stream_name($stream, $policy, $signature, $key_pair_id, $expires) {
    $result = $stream;
    
    $path="";  //Change made here to fix missing $path variable
    
    // if the stream already contains query parameters, attach the new query parameters to the end
    // otherwise, add the query parameters
    $separator = strpos($stream, '?') == FALSE ? '?' : '&';
    // the presence of an expires time means we're using a canned policy
    if($expires) {
        $result .= $path . $separator . "Expires=" . $expires . "&Signature=" . $signature . "&Key-Pair-Id=" . $key_pair_id;
    } 
    // not using a canned policy, include the policy itself in the stream name
    else {
        $result .= $path . $separator . "Policy=" . $policy . "&Signature=" . $signature . "&Key-Pair-Id=" . $key_pair_id;
    }

    // new lines would break us, so remove them
    return str_replace('\n', '', $result);
}

function encode_query_params($stream_name) {
    // the adobe flash player has trouble with query parameters being passed into it, 
    // so replace the bad characters with their url-encoded forms
    return str_replace(
        array('?', '=', '&'),
        array('%3F', '%3D', '%26'),
        $stream_name);
}

function get_canned_policy_stream_name($video_path, $private_key_filename, $key_pair_id, $expires) {
    // this policy is well known by CloudFront, but you still need to sign it, since it contains your parameters
    $canned_policy = '{"Statement":[{"Resource":"' . $video_path . '","Condition":{"DateLessThan":{"AWS:EpochTime":'. $expires . '}}}]}';
    // the policy contains characters that cannot be part of a URL, so we base64 encode it
    $encoded_policy = url_safe_base64_encode($canned_policy);
    // sign the original policy, not the encoded version
    $signature = rsa_sha1_sign($canned_policy, $private_key_filename);
    // make the signature safe to be included in a url
    $encoded_signature = url_safe_base64_encode($signature);

    // combine the above into a stream name
    $stream_name = create_stream_name($video_path, null, $encoded_signature, $key_pair_id, $expires);
    // url-encode the query string characters to work around a flash player bug
    
    // Commented this line there was no need to encode the query params for JW Player 
    //return encode_query_params($stream_name);
    
    return $stream_name;

}

// Path to your private key. Be very careful that this file is not accessible
// from the web!
$private_key_filename = 'key/pk-APKAJC22BYF2JGZTOC6A.pem';
$key_pair_id = 'APKAJC22BYF2JGZTOC6A';
$expires = time () + 300; // 5 min from now
//http://d3sisat5gdssl6.cloudfront.net/MVI_1716.MOV.transcode.mp4
$s3video = 'MVI_1716.MOV.transcode.mp4';
//$s3video = 'MVI_1716.MOV.mp4';
$dhost = 'http://d3sisat5gdssl6.cloudfront.net/';
$shost = 'rtmp://s1u1vmosmx0myq.cloudfront.net/cfx/st/mp4:';
//$shost = 'rtmp://s1u1vmosmx0myq.cloudfront.net/cfx/st/';
?>

<html>

<head>

<title>CloudFront Streaming and Downloads with signed URLs</title>

<script type="text/javascript" src="jwplayer/jwplayer.js"></script>

</head>

<body>

	<h1>Amazon CloudFront Streaming and Downloads with signed URLs</h1>

	<h2>Canned Policy</h2>

	<h3>Expires at <?= gmdate('Y-m-d H:i:s T', $expires) ?></h3>

	<br>

 

 



<?php
// Test Image URL
$image_path = 'http://d1ckv7o9k6o3x9.cloudfront.net/5ec827c4-4301-11e3-85d4-22000a8a1935/images/13833158912013-10-15+21.14.58.jpg';
// $canned_policy_stream_name = get_canned_policy_stream_name($image_path, $private_key_filename, $key_pair_id, $expires);
// Debug
echo '<p><img src="' . $image_path . '"width="200" height="150"/></p>';

$video_download_path = $dhost.$s3video;
$video_streaming_path = $shost.$s3video;
// $video_download_path = 'http://d1ckv7o9k6o3x9.cloudfront.net/VR_MOVIE.mp4';
// $canned_video_download_stream_name = get_canned_policy_stream_name($video_download_path, $private_key_filename, $key_pair_id, $expires);

?>

<p>

		<a href="<?= $video_download_path ?>">This link let's you download and

			watch the file.</a>

	</p>



<?php
//$video_path = '481f0560-e83e-11e2-8fd6-12313909a953/media/web/VID_20130629_215321.mp4';
// - removed //$video_path = 'VR_MOVIE.mp4';
 $canned_policy_stream_name = $shost.get_canned_policy_stream_name($s3video, $private_key_filename, $key_pair_id, $expires);
?>

<!-- Note download links without security don't care if you add a signed URL -->

	<!-- This code is good with signed URLs -->

	<div id='player_1'></div>

	<script type='text/javascript'>

  jwplayer('player_1').setup({

    //Use this URL to access with security

    //file: "rtmp://s1iq2cbtodqqky.cloudfront.net/cfx/st/mp4:18382cb7-c9d3-47f0-b5e7-a00f479f39ee/media/web/small-2.mp4.mp4",
    file: "rtmp://s1iq2cbtodqqky.cloudfront.net/cfx/st/mp4:932fae65-56b4-44c2-b01e-692b45fe6e78/media/web/MVI_4036.mp4",
    
    //file: "<?php echo $video_streaming_path; ?>",
    //file: "<?php echo $canned_policy_stream_name; ?>",
    width: "480",

    height: "270"

  });

</script>

	&nbsp;



	<BR>

	<BR>

	<BR>



</body>

</html>

