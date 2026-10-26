var isATR2 = true; 

function checkProtocol(gotoLink) {
    if (!/^https?:\/\//i.test(gotoLink)) {
        gotoLink = "https://" + gotoLink;
    }
    return gotoLink;
}

async function getRecordWithMidSn(ACID, mid, sn, eventData, mfaccessid) {
    let success = false;
    
    let error_msg = '';
    let result = {};

    let record = null;

    try {
        if (Number(ACID) <= Number.MAX_SAFE_INTEGER) {
            let usps_mid = (+mid === +mid && !mid.startsWith("0")) ? +mid : mid; // Convert string value of mid to a number, only if it's a number and does NOT start with "0".
            let usps_sn = (+sn === +sn && !sn.startsWith("0")) ? +sn : sn; // Same for sn.

            const postData = { mid: usps_mid, serialnumber: usps_sn };
            let canFind = false;
            try {
                // Retrieve and parse the stored record
                record = await _MFS.getRecord(ACID, postData, 60, "", mfaccessid);
                canFind = true;
            } catch (error) {
                console.log("Record not found is undefined or null");
                canFind = false;
            }            
            if (!canFind) {
                error_msg = "The Serial Number or MID you entered could not be found. Please double-check your entry and try again.";                        
                success = false;
            } else {
                console.log("record = ", record);
                if (eventData !== null) {
                    try {
                        console.log("eventData = ", eventData);
                        let addResult = await record.addEvent(eventData);       

                        console.log("addResult = ", addResult);                                              
                    } catch (error3) {
                        console.log("addEvent error:", error3);        
                    }
                }
                success = true;
            }                
        } else {
            console.warn("Account number exceeds safe integer range!");
        }  
    } catch (error2) {
        console.log("record not found is undefined or null:", error2);        
        error_msg = "The Serial Number or MID you entered could not be found. Please double-check your entry and try again.";
        success = false;
    }
    result['success'] = success;
    result['record'] = record;
    result['error_msg'] = error_msg;
    console.log("result = ", result);                                     
    return result;
}

function getInformRedirect(url){
    const index = url.indexOf("/informed.html");
    const baseUrl = index !== -1 ? url.slice(0, index) : url;
    return baseUrl; 
}


async function getRecord(ACID, purl, mfaccessid,eventData) {
    let success = false;    
    let error_msg = '';
    let result = {};
    let record = null;
    try {      
        const postData = { purl: purl };
        let canFind = false;
        try {
            // Retrieve and parse the stored record                
            record = await _MFS.getRecord(ACID, postData, 60, purl, mfaccessid);
            canFind = true;
        } catch (error) {
            console.log("Record not found is undefined or null");
            canFind = false;
        }            
        if (!canFind) {
            error_msg = "The purl you entered could not be found. Please double-check your entry and try again.";                        
            success = false;
        } else {
            console.log("record = ", record);            
			if (eventData !== null) {
				try {
					console.log("eventData = ", eventData);
					let addResult = await record.addEvent(eventData);       
					console.log("addResult = ", addResult);                                              
				} catch (error3) {
					console.log("addEvent error:", error3);        
				}
			}
			success = true;

        }                
          
    } catch (error2) {
        console.log("record not found is undefined or null:", error2);        
        error_msg = "The purl you entered could not be found. Please double-check your entry and try again.";
        success = false;
    }       
    result['success'] = success;
    result['record'] = record;
    result['error_msg'] = error_msg;
    console.log("result = ", result);                                     
    return result;
}

async function createEventWtPURL(mfaccessid, ACID, app_code, event_code, event_element, purl, event_path) {
    let eventData = {
        "app_code": app_code,
        "event_code": event_code,
        "event_path": event_path,
        "event_element": event_element,
        "event_date": new Date().toISOString(),					            
        "shardKey" : purl				
    }
	let result = null; 
	result = await getRecord(ACID, purl, mfaccessid,eventData); 
	console.log("createEvent wt purl = " , result); 
    return result;  
}

async function createEventDataPURL(mfaccessid, ACID, app_code, event_code, event_element, purl, event_path, event_data) {
    let eventData = {
        "app_code": app_code,
        "event_code": event_code,
        "event_path": event_path,
        "event_element": event_element,
        "event_data": event_data,
        "event_date": new Date().toISOString(),					            
        "shardKey" : purl				
    }
	let result = null; 
	result = await getRecord(ACID, purl, mfaccessid,eventData); 
	console.log("createEventDataPURL result = " , result); 
    return result;  
}


function cleanSharpVariable(value) {
	  return value.startsWith("##") && value.endsWith("##") ? "" : value;
}

function clearifyPage(page,purl){
    console.log("loadpage : ", page ); 	
	if(page == "welcome.html" && purl){
		page = "purl.html"; 
	}
	return page; 
}

function getEventDataByPage(page) {
	  const mapping = {
		  /*
		 "gurl.html": {
		  channel: "GURL",
		  FilterId: "GURL",
		  app_code: "501",
		  event_code: "50120",
		  event_element: "gurl.html"
		},*/ 
		"purl.html": {
		  channel: "PURL",
		  FilterId: "PURL",
		  app_code: "501",
		  event_code: "50121",
		  event_element: "p.html"
		},
		"qr.html": {
		  channel: "QR Code",
		  FilterId: "QR",
		  app_code: "501",
		  event_code: "50122",
		  event_element: "qr.html"
		},
		"id.html": {
		  channel: "Informed Delivery",
		  FilterId: "ID",
		  app_code: "501",
		  event_code: "50123",
		  event_element: "id.html"
		},				
		"email.html": {
		  channel: "Email",
		  FilterId: "EMAIL",
		  app_code: "501",
		  event_code: "50124",
		  event_element: "email.html"
		},
		"survey.html": {
		  channel: "Survey",
		  FilterId: "SURVEY",
		  app_code: "501",
		  event_code: "50125",
		  event_element: "survey.html"
		},	
		"thankyou.html": {
		  channel: "Thankyou",
		  FilterId: "THANKYOU",
		  app_code: "501",
		  event_code: "50126",
		  event_element: "thankyou.html"
		}
	  };

	  return mapping[page] || {
		channel: "GURL",
		FilterId: "",
		app_code: "",
		event_code: "",
		event_element: page
	  };
}

function getEventPath(purl){	
	let event_path1 = window.location.href.split(/[?#]/)[0]; 
	event_path1 = event_path1.replace(/^(https?:\/\/)?(www\.)?/, "");

	if(purl){
		let index = event_path1.indexOf("/"+purl);
		if (index !== -1) {
			event_path1 = event_path1.substring(0, index);
		}else{  //case begin with purl 
			event_path1 = event_path1.replace(/\/[^/]+\.[a-z0-9]+$/i, "");        // remove last xxx.html
			let prefix = purl+"."; 
			if (event_path1.startsWith(prefix)) {  
				event_path1 = event_path1.slice(prefix.length);
			}
		}
	}
	event_path1 = event_path1.replace(/\/$/, "");
	console.log("event_path = " , event_path1); 
	return event_path1
}



function createEventDataByPage(page) {
	  const mapping = {
		  /*
		 "gurl.html": {
		  channel: "GURL",
		  FilterId: "GURL",
		  app_code: "501",
		  event_code: "50120",
		  event_element: "gurl.html"
		},*/ 
		"purl.html": {
		  channel: "PURL",
		  FilterId: "PURL",
		  app_code: "501",
		  event_code: "50121",
		  savepage: "p.html"
		},
		"qr.html": {
		  channel: "QR Code",
		  FilterId: "QR",
		  app_code: "501",
		  event_code: "50122",
		  savepage: "qr.html"
		},
		"id.html": {
		  channel: "Informed Delivery",
		  FilterId: "ID",
		  app_code: "501",
		  event_code: "50123",
		  savepage: "id.html"
		},				
		"email.html": {
		  channel: "Email",
		  FilterId: "EMAIL",
		  app_code: "501",
		  event_code: "50124",
		  savepage: "email.html"
		},
		"survey.html": {
		  channel: "Survey",
		  FilterId: "SURVEY",
		  app_code: "501",
		  event_code: "50125",
		  savepage: "survey.html"
		},	
		"thankyou.html": {
		  channel: "Thankyou",
		  FilterId: "THANKYOU",
		  app_code: "501",
		  event_code: "50126",
		  savepage: "thankyou.html"
		}
	  };

	  return mapping[page] || {
		channel: "GURL",
		FilterId: "",
		app_code: "",
		event_code: "",
		savepage: page
	  };
}

async function AddEventOnLoadPage(purl, pageName){
    const url = new URL(window.location.href);   
	let event_path = getEventPath(purl); 

    const event_data = {};
    url.searchParams.forEach((value, key) => {		
		const lowerKey = key.toLowerCase();
		if (lowerKey !== "sessionguid" && lowerKey !== "cookiecheck") {
			event_data[lowerKey] = (value || "").toLowerCase();
		}

    });
	console.log("AddEventOnLoadPage:: event data = ", event_data); 

    let event_element = "";
	let { channel, FilterId, app_code, event_code, savepage } = createEventDataByPage(pageName);	

    if (event_data.mf_source) {
        event_element = event_data.mf_source;

    } else if (savepage && savepage !== "") {
        event_element = savepage;
    } else {      
        event_element = "Direct Mail";
    }

	if (app_code === ""){
		console.log("No Event app_code:", app_code); 
	}else{		
		let result = await createEventDataPURL(window._mfaccessid, window._mfaid, app_code, event_code, event_element, purl, event_path, event_data);
		return result;
	}    
}

async function AddEventOnLoadPageByEventCode(purl, pageName, app_code_in, event_code_in){	
	return await AddEventOnLoadPageByEventAll(purl, pageName, app_code_in, event_code_in,"",""); 
}

async function AddEventOnLoadPageByEventAll(purl, pageName, app_code_in, event_code_in, event_path_in, event_element_in){	

	let app_code_post = app_code_in;
	let event_code_post = event_code_in;
    let event_element = event_element_in;

	const url = new URL(window.location.href);   
	let event_path = getEventPath(purl);
	if(event_path_in){
		event_path = event_path_in; 
	}

    const event_data = {};
    url.searchParams.forEach((value, key) => {		
			const lowerKey = key.toLowerCase();
			if (lowerKey !== "sessionguid" && lowerKey !== "cookiecheck") {
				event_data[lowerKey] = (value || "").toLowerCase();
			}
    });
	console.log("AddEventOnLoadPageByEvent:: event data = ", event_data); 


	let { channel, FilterId, app_code, event_code, savepage } = createEventDataByPage(pageName);

	if (app_code_post == '') {
		app_code_post = app_code;
	}
	if (event_code_post == '') {
		event_code_post = event_code;
	}
	if(!event_element){
		if (event_data.mf_source) {
			event_element = event_data.mf_source;

		} else if (savepage && savepage !== "") {
			event_element = savepage;
		} else {      
			event_element = "Direct Mail";
		}
	}
	if (app_code_post === ""){
		console.log("No Event app_code:", app_code_post); 
	}else{		
		let result = await createEventDataPURL(window._mfaccessid, window._mfaid, app_code_post, event_code_post, event_element, purl, event_path, event_data);
		return result;
	}	
}
