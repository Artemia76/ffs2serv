/**
 * Created by Flo on 10/07/2016.
 */
var getHttpRequest = function () {
    var httpRequest = false;

    if (window.XMLHttpRequest) { // Mozilla, Safari,...
        httpRequest = new XMLHttpRequest();
        if (httpRequest.overrideMimeType) {
            httpRequest.overrideMimeType('text/xml');
        }
    }
    else if (window.ActiveXObject) { // IE
        try {
            httpRequest = new ActiveXObject("Msxml2.XMLHTTP");
        }
        catch (e) {
            try {
                httpRequest = new ActiveXObject("Microsoft.XMLHTTP");
            }
            catch (e) {
            }
        }
    }

    if (!httpRequest) {
        alert('Abandon :( Impossible de créer une instance XMLHTTP');
        return false;
    }

    return httpRequest
}

var direct_request_post = function (url, string, async_sync) {
    async_sync = async_sync || true;
    var xhr = getHttpRequest();


    xhr.onreadystatechange = function () {

        if (xhr.readyState === 4 && xhr.status == 200) {
            result=xhr.responseText;
         //alert('test:'+result);
            //return xhr.responseText;
        }
    };
    xhr.open('POST', url , async_sync);
    xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhr.send(string);

};