
function readCookie(name) {
	var nameEQ = name + "=";
	var ca = document.cookie.split(';');
	for(var i=0;i < ca.length;i++) {
		var c = ca[i];
		while (c.charAt(0)==' ') c = c.substring(1,c.length);
		if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
	}
	return null;
}

function createCookie(name,value,days) {            
	if (days) {
		var date = new Date();
		date.setTime(date.getTime()+(days*24*60*60*1000));
		var expires = "; expires="+date.toGMTString();
	} else {
                          var expires = "";
                     }
	document.cookie = name+"="+value+expires+"; path=/";
}

function deleteCookie ( cookie_name )
{
  createCookie(cookie_name,"",-1); 
}

function getDatetime(){
	var startDate = '';
	var date = new Date(); // 06/21/2013 03:39:50 PM
						
	var years = date.getFullYear();
	var days = date.getDate();
	var hours = date.getHours();
	var minutes = date.getMinutes();
	var month = date.getMonth() + 1;
	var suffix = 'AM';
	if (hours >= 12)
	{
		suffix = 'PM';
		hours = hours - 12;
	}
	if (hours == 0)
	{
		hours = 12;
	}


	if (minutes < 10)
		minutes = '0' + minutes;

	if (month < 10)
		month = '0' + month;

	if (days < 10)
		days = '0' + days;
	
	if (hours < 10)
		hours = '0' + hours;

	

	startDate = month+'/'+days+'/'+years+' '+hours+':'+minutes+':00'+' '+suffix;
	return startDate;
}

function getProjectURL(){
	var protocol = window.location.protocol;
	var host = window.location.host;
	var path = window.location.pathname;
	var n=path.split("/");
	var newpath = '';
	for(var i=0;i < n.length-1;i++) {
		newpath = newpath+n[i]+'/';
	}

	var url = protocol+'//'+host+newpath;
	
	return url;

}

 function getParameterByName(name){
      name = name.replace(/[\[]/, "\\\[").replace(/[\]]/, "\\\]");
      var regexS = "[\\?&]" + name + "=([^&#]*)";
      var regex = new RegExp(regexS);
      var results = regex.exec(window.location.search);
      if(results == null)
        return "";
      else
        return decodeURIComponent(results[1].replace(/\+/g, " "));
    }

function logout() {
	deleteCookie("loginEmail");
	deleteCookie("loginPassword");
	deleteCookie("xmlFile");	
	deleteCookie("canLogin");
	location.href = 'login.html';
}

function superAdminlogout() {
	location.href = 'superAdminLogin.html';
}

function contentClick() {
	
	alert('contentClick');
}

function loadAccount() {
	var email = readCookie("loginEmail");
	var pwd = readCookie("loginPassword");		
	return loadAccountParam(email, pwd);
	
}

function loadAccountParam(email, pwd) {
			
	var data = '<option value=""></option>';
	var projecturl = getProjectURL();
	var ret = false;
	var json = $.getJSON(projecturl+"login.php?callback=?", {
		email: email,
		pwd: pwd
	})
	.success(function(json) {
		ret = true;
		if( json.success ) {			
			if (json.mps) {
				for( var i = 0 ; i < json.mps.length ; i++ ) {
					data += '<option value="'+ json.mps[i][0] +'">'+ json.mps[i][1] +'</option>';
				}					
			}
			if (data != "") {
				$('#account').html(data);
				Sort('account');
			
			}				
		} else {
			alert(json.message);
			ret = false;
		}
	})
	.error(function() {
	  alert("Oops, something happened. Please refresh the page and I will likely stop complaining.  [Geek reference: global.js]");
	  
	});
	
	return ret;
}

function Sort(elementId) {
    // Convert the listbox options to a javascript array and sort (ascending)
    var sortedList = $.makeArray($("#" + elementId + " option"))
        .sort(function(a, b) {
            return $(a).text().toLowerCase() < $(b).text().toLowerCase() ? -1 : 1;
        });
    // Clear the options and add the sorted ones
    $("#" + elementId).empty().html(sortedList);
}

function checkEmail(email) {   
	var reg = /^([A-Za-z0-9_\-\.])+\@([A-Za-z0-9_\-\.])+\.([A-Za-z]{2,4})$/; 
	if(reg.test(email) == false) {   
		//alert('Invalid Email Address'); 
		return false; 
	} else {
		return true;
	}
}

function getRemote(remote_url) {
	return $.ajax({
		type: "GET",
		dataType: 'json',
		url: remote_url,
		async: false,
	}).responseText;
}


function serealizeSelects (select)
{
    var array = [];
    select.each(function(){ array.push($(this).val()) });
    return array;
}
Array.prototype.getIndexByValue = function (name, value) {
		for (var i = 0, len=this.length; i <len; i++) {
			if (this[i][name] || this[i][name] === 0) {
				if (this[i][name] === value) {
					return i
				}
			}
		}
		return -1;
};

Array.prototype.getIndexByValueLowerCase = function (name, value) {
	var val = value.toLowerCase();
	for (var i = 0, len=this.length; i <len; i++) {
		if (this[i][name]) {
			var arrVal = this[i][name].toLowerCase();
			if (arrVal === val) {
				return i
			}
		}
	}
	return -1;
};

Array.prototype.remove = function(from, to) {
  var rest = this.slice((to || from) + 1 || this.length);
  this.length = from < 0 ? this.length + from : from;
  return this.push.apply(this, rest);
};
