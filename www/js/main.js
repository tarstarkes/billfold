(function($) {
/*doAJAX(dta, ajax_action, successCallback, failureCallback)
This function is meant to make ajax calls more automated so that you
do not need to make an ajax call every time you need to send something
to php, instead this function handles the request and returns the data
returned from php.
*/
function doAJAX(dta, ajax_action, successCallback, failureCallback) {
	var ajaxURL = 'index.php';
	console.log("AJAX:  Action= '"+ajax_action+"' dta=",dta); //show request in console
	$.ajax({
		type: "POST",
		url:"index.php",
		data: dta,
		dataType: 'jsonp',
		success:function(response) {
			if (response.success) {
				/*success callback function for doAJAX, 'returns' 
				data back to the function that called it.
				without a return statement*/
				successCallback(response, ajax_action); 
			}
			else {
				if ( typeof(failureCallback) === "function" ) {
					/*failure callback function for doAJAX*/
					failureCallback();
				}
				else{
					console.log("AJAX call: "+ajax_action+" failed. dta=",dta," response=",response);
				}
			}
		},
		error:function(e){
			console.log('AJAX Request Failed: error status => '+e.status+', error statusText => '+e.statusText);
		}
	});
} //end doAJAX()

$(document).ready(function(e) {
	//register
	$('input[name="reg"]').on('click', function(e){
		var values = $('#regForm').children();
		if(document.getElementsByName('email')[0].value == document.getElementsByName('confEmail')[0].value && document.getElementsByName('pass')[0].value == document.getElementsByName('confPass')[0].value){
			$('#problem h3').html('');
			var dta = {
				action: 'register',
				fname: document.getElementsByName('fName')[0].value,
				lname: document.getElementsByName('lName')[0].value,
				email: document.getElementsByName('email')[0].value,
				pass: document.getElementsByName('pass')[0].value,
				valid: 0,
				secretQ: document.getElementsByName('secretQ')[0].value,
				secretA: document.getElementsByName('secretA')[0].value
			};
			doAJAX(dta, 'register', function(e){
				$('body').html(e.html);
			});
		}
		else{
			$('#problem h3').html('Please ensure that your password and email fields match.');
		}
	});
	
	$('input[name="login"]').on('click', function(e){
		var usr = $("#user").val();
		var pass = $("#pass").val();
		var dta = {
			action: 'login',
			user: usr,
			pass: pass
		};
		doAJAX(dta, 'login', function(e){
			//Success
		});
	});
	
	$('.addNew').on('click', function(e){
		$('.addNew').css({'opacity':'.8', 'color':'#e0e0e0'});
		setTimeout(function(){
			$('.addNew').css({'opacity':'1', 'color':'#000000'});
			newAccount();
		}, 200);
	});
	
	$('input[name="cancelWindow"]').on('click', function(e){
		closeAccWindow();
	});
	
	$('.submitButtons').on('click', function(e){
		createAccount();
	});
	
	$('.profIcon').on('click', function(e){
		$('#logout').animate({
			height: 'toggle'
		}, 300, function(){
			//animation complete
		});
	});

});
/***********************************************************************
						FUNCTIONS
***********************************************************************/
function newAccount(){
	
	$('.popup').css('display', 'block');
	$('.newAccountForm').css('display', 'block');
	$('.newAccountForm').animate({
		top: '12.5vh'
	}, 500,function(){
		//animation completes
	});
}
function closeAccWindow(){
	$('.newAccountForm').animate({
		top: '-100vh'
	}, 500,function(){
		$('.popup').css('display', 'none');
		$('.newAccountForm').css('display', 'none');
	});
}


}(jQuery));