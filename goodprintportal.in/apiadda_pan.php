<!DOCTYPE html>
<html>
<head>
    <title>🔥 PAN Finder</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        body {
            font-family: Arial;
            background: linear-gradient(135deg, #020617, #0f172a);
            color: #fff;
            text-align: center;
        }

        .box {
            background: #1e293b;
            padding: 25px;
            margin: 50px auto;
            width: 90%;
            max-width: 420px;
            border-radius: 15px;
            box-shadow: 0 0 25px rgba(0,0,0,0.7);
        }

        input {
            width: 90%;
            padding: 12px;
            margin: 10px;
            border-radius: 8px;
            border: none;
            outline: none;
        }

        button {
            padding: 12px 20px;
            border: none;
            background: #22c55e;
            color: #fff;
            border-radius: 8px;
            cursor: pointer;
            width: 90%;
        }

        button:hover {
            background: #16a34a;
        }

        .hidden { display: none; }

        .result {
            margin-top: 20px;
            background: #020617;
            padding: 15px;
            border-radius: 10px;
            text-align: left;
        }

        .footer {
            margin-top: 20px;
            font-size: 12px;
            color: #94a3b8;
        }
    </style>
</head>

<body>

<div class="box">
    <h2>🔥 PAN Finder</h2>

    <input type="text" id="phone" placeholder="Enter Mobile Number">
    <button onclick="sendOTP()">Send OTP</button>

    <div id="otpBox" class="hidden">
        <input type="text" id="otp" placeholder="Enter OTP">
        <input type="text" id="fname" placeholder="First Name">
        <input type="text" id="lname" placeholder="Last Name">
        <button onclick="verifyOTP()">Verify & Get PAN</button>
    </div>

    <div id="result" class="result hidden"></div>

    <div class="footer">
        Powered by <b>Lala Web Developer</b> & <b>APIADDA</b>
    </div>
</div>

<script>
let state = "";

// SEND OTP
function sendOTP() {
    let phone = document.getElementById("phone").value;

    let formData = new FormData();
    formData.append("action", "send_otp");
    formData.append("phone_number", phone);

    fetch("api.php", {
        method: "POST",
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        alert(data.message);
        if(data.status === "success"){
            state = data.state;
            document.getElementById("otpBox").classList.remove("hidden");
        }
    });
}

// VERIFY OTP
function verifyOTP() {
    let phone = document.getElementById("phone").value;
    let otp = document.getElementById("otp").value;
    let fname = document.getElementById("fname").value;
    let lname = document.getElementById("lname").value;

    let formData = new FormData();
    formData.append("action", "validate_otp");
    formData.append("phone_number", phone);
    formData.append("otp", otp);
    formData.append("state", state);
    formData.append("first_name", fname);
    formData.append("last_name", lname);

    fetch("api.php", {
        method: "POST",
        body: formData
    })
    .then(res => res.json())
    .then(data => {

        if(data.status === "success") {
            let d = data.data.meta_info;

            document.getElementById("result").innerHTML = `
                <h3>✅ PAN Details</h3>
                <p><b>Name:</b> ${d.name}</p>
                <p><b>PAN:</b> ${d.pan_number}</p>
                <p><b>DOB:</b> ${d.dob}</p>
                <p><b>Mobile:</b> ${d.phone_number}</p>
            `;

            document.getElementById("result").classList.remove("hidden");
        } else {
            alert("Error: " + data.message);
        }
    });
}
</script>

</body>
</html>