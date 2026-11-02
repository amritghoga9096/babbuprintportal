var portNumber = 11100,
    maxPortNumber = 11120,
    baseUri = "http://127.0.0.1:",
    deviceInfoPath = baseUri + "rd/getDeviceInfo",
    capturePath = baseUri + "rd/capture",
    deviceInfo = {
        manufacturer: "",
        model: "",
        srno: ""
    },
    ready = !1,
    isIntegraService = !1,
    scanCompleted = !1;

function startCaptureRD(e, r) {
    var o = "Use following possible steps to resolve the issue:<br>1. Register your device from vendor or using Register link.<br>2. Download and install the latest registered device drivers.<br>3. Start/ restart the registered device service.";
    rdServiceInfo(e.isHttpsService, function() {
        ready ? rdCapture(e, function(t, s) {
            rdDeviceInfo(function() {
                if (200 === t) {
                    s = String(s).replace(/(\r\n|\n|\r)/gm, "").trim();
                    var e = $($.parseXML(s)).find("Resp");
                    "0" === e.attr("errCode") ? r({
                        status: "success",
                        data: s,
                        manufacturer: deviceInfo.manufacturer,
                        model: deviceInfo.model,
                        sn: deviceInfo.srno
                    }) : r({
                        status: "failed",
                        data: "Error: " + e.attr("errInfo") + " (" + e.attr("errCode") + ")",
                        manufacturer: deviceInfo.manufacturer,
                        model: deviceInfo.model,
                        sn: deviceInfo.srno
                    })
                } else r({
                    status: "failed",
                    data: o,
                    manufacturer: deviceInfo.manufacturer,
                    model: deviceInfo.model,
                    sn: deviceInfo.srno
                })
            })
        }) : scanCompleted && r({
            status: "failed",
            data: o = "Register your device from device vendor.<center><b>OR</b></center>Remove & plug in device again.",
            manufacturer: deviceInfo.manufacturer,
            model: deviceInfo.model,
            sn: deviceInfo.srno
        })
    })
}

function rdServiceInfo(t, s) {
    !0 === t && (baseUri = "https://127.0.0.1:"), portNumber === maxPortNumber && (scanCompleted = !0);
    var r = GetXmlHttpRequest();
    r.open("RDSERVICE", baseUri + portNumber, !0), r.onload = function() {
        if (console.log("PORT SCANNING: " + portNumber), 200 === r.status) {
            var e = $($.parseXML(String(r.response).replace(/(\r\n|\n|\r)/gm, "").trim())).find("RDService");
            e ? (console.log("SERVICE RUNNING: " + $(e).attr("info").toUpperCase() + ", STATUS: " + $(e).attr("status").toUpperCase()), "READY" === $(e).attr("status").toUpperCase() ? ready = !0 : (portNumber++, rdServiceInfo(t, s)), deviceInfoPath = baseUri + portNumber + $(e).find("Interface[id='DEVICEINFO']").attr("path"), capturePath = baseUri + portNumber + $(e).find("Interface[id='CAPTURE']").attr("path"), isIntegraService = -1 < $(e).attr("info").toUpperCase().indexOf("INTEGRA"), s(r.status, r.response)) : portNumber < maxPortNumber ? (portNumber++, rdServiceInfo(t, s)) : (scanCompleted = !0, s(r.status, r.response))
        } else portNumber < maxPortNumber ? (portNumber++, rdServiceInfo(t, s)) : (console.log("Status: " + r.status + ", Response: " + r.response), s(r.status, r.response))
    }, r.onerror = function() {
        console.log("PORT SCANNING: " + portNumber), console.log("OnError- Status: " + r.status + ", Response: " + r.response), portNumber < maxPortNumber ? (portNumber++, rdServiceInfo(t, s)) : s(r.status, r.response)
    }, r.ontimeout = function() {
        console.log("OnTimeOut- Status: " + r.status + ", Response: " + r.response), s(r.status, r.response)
    }, r.abort = function() {
        console.log("OnAbort- Status: " + r.status + ", Response: " + r.response), s(r.status, r.response)
    }, r.send()
}
var rdDeviceInfo = function(r) {
        var o = GetXmlHttpRequest();
        o.open("DEVICEINFO", deviceInfoPath, !0), o.onload = function() {
            if (200 === o.status) {
                var e = $($.parseXML(String(o.response).replace(/(\r\n|\n|\r)/gm, "").trim())).find("DeviceInfo");
                if (e) {
                    var t = e.attr("srno");
                    if (!t) {
                        var s = -1 < e.attr("dpId").toLowerCase().indexOf("morpho") ? "serial_number" : "srno";
                        (t = $(e).find("additional_info").find("Param[name='" + s + "']").attr("value")) || (t = 0)
                    }
                    "INTEGRA.IMSPL" === (deviceInfo = {
                        manufacturer: e.attr("dpId"),
                        model: e.attr("mi"),
                        srno: t
                    }).manufacturer && (-1 < deviceInfo.model.indexOf("CSD2") ? deviceInfo = {
                        manufacturer: "cogent",
                        model: "CSD200",
                        srno: null
                    } : -1 < deviceInfo.model.indexOf("4500") ? deviceInfo = {
                        manufacturer: "digitalPersona",
                        model: "4500",
                        srno: null
                    } : -1 < deviceInfo.model.indexOf("1300E") && (deviceInfo = {
                        manufacturer: "morpho",
                        model: "1300E",
                        srno: null
                    }))
                } else console.log("Status: " + o.status + ", Response: " + o.response)
            } else console.log("Status: " + o.status + ", Response: " + o.response);
            r(o.status, o.response)
        }, o.onerror = function() {
            console.log("OnError- Status: " + o.status + ", Response: " + o.response), r(o.status, o.response)
        }, o.ontimeout = function() {
            console.log("OnTimeOut- Status: " + o.status + ", Response: " + o.response), r(o.status, o.response)
        }, o.abort = function() {
            console.log("OnAbort- Status: " + o.status + ", Response: " + o.response), r(o.status, o.response)
        }, o.send()
    },
    rdCapture = function(e, t) {
        var s = GetXmlHttpRequest();
        s.open("CAPTURE", capturePath, !0), isIntegraService || (s.setRequestHeader("Content-Type", "text/xml"), s.setRequestHeader("Accept", "text/xml"));
        var r = "",
            o = "";
        isIntegraService && (o = "<Demo></Demo>"), "ekyc" === e.authType.toLowerCase() && (r = "E0jzJ/P8UopUHAieZn8CKqS4WPMi5ZSYXgfnlfkWjrc=");
      
//var n = "<PidOptions> <Opts fCount=\"1\" fType=\"0\" iCount=\"0\" pCount=\"0\" format=\"0\" pidVer=\"2.0\" timeout=\"20000\" otp=\"\" posh=\"LEFT_INDEX\" env=\"P\" wadh=\'E0jzJ/P8UopUHAieZn8CKqS4WPMi5ZSYXgfnlfkWjrc=\' /> <Demo></Demo>  </PidOptions>";
var n = "<PidOptions  ver=\"1.0\"> <Opts fCount=\"1\" fType=\"2\" pCount=\"0\" format=\"0\" pidVer=\"2.0\" wadh=\'E0jzJ/P8UopUHAieZn8CKqS4WPMi5ZSYXgfnlfkWjrc=\' timeout=\"20000\"  posh=\"UNKNOWN\" env=\"P\"  /> </PidOptions>";
	   s.onload = function() {
            200 === s.status || console.log("Status: " + s.status + ", Response: " + s.response), t(s.status, s.response)
        }, s.onerror = function() {
            console.log("OnError- Status: " + s.status + ", Response: " + s.response), t(s.status, s.response)
        }, s.ontimeout = function() {
            console.log("OnTimeOut- Status: " + s.status + ", Response: " + s.response), t(s.status, s.response)
        }, s.abort = function() {
            console.log("OnAbort- Status: " + s.status + ", Response: " + s.response), t(s.status, s.response)
        }, s.send(n)
    };

function GetXmlHttpRequest() {
    return window.XMLHttpRequest ? new XMLHttpRequest : new ActiveXObject("Microsoft.XMLHTTP")
}