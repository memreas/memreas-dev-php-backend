/////////////////////////////////
// Author: John Meah
// Copyright memreas llc 2013
/////////////////////////////////

//var base_url = 'http://memreasdev.elasticbeanstalk.com/eventapp_zend2.1/webservices/index.php?';
var paypal_url = '/index/paypal';
var payPalListCards_url = '/index/paypalListCards';
var payPalDeleteCards_url = '/index/paypalDeleteCards';
var payPalAddValue_url = '/index/payPalAddValue';
var payPalDecrementValue_url = '/index/payPalDecrementValue';
var payPalAccountHistory_url = '/index/payPalAccountHistory';

//onEmailFocusOut = function () {
//    if (!isValidEmail($('#inEmail').val())) {
//        alert("Please check your email address");
//    }
//}
//onUserNameFocusOut = function () {
//    jQuery.checkUserName();
//}

//function isValidEmail($email) {
//  var emailReg = /^([\w-\.]+@([\w-]+\.)+[\w-]{2,4})?$/;
//  if( !emailReg.test( $email ) ) {
//    return false;
//  } else {
//    return true;
//  }
//}

/////////////////////
// Store Card
/////////////////////
jQuery.paypalStoreCard = function () {

	var obj = new Object();
	obj.first_name = $("#first_name").val();
	obj.last_name = $("#last_name").val();
	obj.credit_card_type = $("#credit_card_type").val();
	obj.credit_card_number = $("#credit_card_number").val();
	obj.expiration_month = $("#expiration_month").val();
	obj.expiration_year = $("#expiration_year").val();
	obj.address_line_1 = $("#address_line_1").val();
	obj.address_line_2 = $("#address_line_2").val();
	obj.city = $("#city").val();
	obj.state = $("#state").val();
	obj.zip_code = $("#zip_code").val();
    var json_paypalStoreCard = JSON.stringify(obj);
    var data = "";
    
    data = '{"action": "paypalStoreCard", ' + 
    '"type":"jsonp", ' + 
    '"json": ' + json_paypalStoreCard  + 
    '}';
   
	//var results += json_paypalStoreCard;
	$("#results").val(json_paypalStoreCard, null, '\t');
	
	//xml_check_username = "<xml><checkusername><username>" + $('#inUserName').val() + "</username></checkusername></xml>";
	//json_check_username = '{"xml": {"checkusername": { "username": "' + $('#inUserName').val() + '" }}}';
	//data = '{"action": "checkusername", "type":"jsonp", "json":{"xml": { "checkusername": {"username": "' + $('#inUserName').val() + '"}}}}';

	$.ajax( {
	  type:'post', 
	  url: paypal_url,
	  dataType: 'jsonp',
	  data: 'json=' + data,
	  success: function(json){
	  	var req_resp = $("#results").val() + "\n\n" + JSON.stringify(json, null, '\t');
	  	$("#results").val(req_resp);
	  },
	  error: function (jqXHR, textStatus, errorThrown) {
	  	//alert("Inside error jqXHR...");
       	alert(jqXHR.responseText);
       	alert(jqXHR.status);
    	//alert(textStatus);
       	//alert(errorThrown);
	  }
	});
	return false;
}

