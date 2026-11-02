<?php
include('./includes/config.php');

$messageSent = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send'])) {
    $name = filter_var(trim($_POST['name']), FILTER_SANITIZE_STRING);
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $subject = filter_var(trim($_POST['subject']), FILTER_SANITIZE_STRING);
    $message = filter_var(trim($_POST['message']), FILTER_SANITIZE_STRING);

    if (!$name || !$email || !$subject || !$message) {
        $error = "Please fill all fields correctly.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        $to = ahkweb('webemail') ?: 'your-email@example.com';
        $email_subject = "Contact Form: " . $subject;
        $email_body = "You have received a new message from your website contact form.\n\n".
                      "Name: $name\nEmail: $email\nSubject: $subject\nMessage:\n$message\n";

        $headers = "From: $name <$email>\r\n";
        $headers .= "Reply-To: $email\r\n";

        if (mail($to, $email_subject, $email_body, $headers)) {
            $messageSent = true;
        } else {
            $error = "Oops! Something went wrong, please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Contact Us | <?php echo ahkweb('webname'); ?></title>
  	<meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="keywords" content="AADHAR BEST PRINT | PRINT PORTAL | aadhaar print portal | print portal xyz | digital fast print | balaji print portal | harshit print portal | best print portal | PRINT PORTAL | AADHAR PRINT PORTAL | AADHAAR PRINT PORTAL | SHIVYOG PRINT PORTAL | DIGITAL FAST PRINT | BALAJI PRINT PORTAL | AADHAAR PRINT PORTAL FINGERPRINT | ADVANCE AADHAAR PRINT PORTAL | SK PRINT PORTAL | RAVI PRINT PORTAL | BEST PRINT PORTAL | BEST AADHAAR PRINT PORTAL | FAST PRINT PORTAL | MY RAVI PRINT PORTAL | PRINT PORTAL AADHAR | DIGITAL PRINT PORTAL | SN PRINT PORTAL | FREE PRINT PORTAL | SUPER PVC PRINT PORTAL | VLE PRINT PORTAL | PK PRINT PORTAL | INSTANT PRINT PORTAL | MILAN PRINT PORTAL | PRINT PORTAL REGISTRATION | ONLINE PRINT PORTAL | PVC PRINT PORTAL | MY PRINT PORTAL | SHIV PRINT PORTAL | AADHAAR PRINT PORTAL FREE | AADHAAR PRINT PORTAL XYZ | AADHAR CARD PRINT PORTAL | AADHAAR PRINT PORTAL REGISTRATION | NEW PRINT PORTAL | FREE PRINT PORTAL XYZ | FREE AADHAAR PRINT PORTAL | UID PRINT PORTAL | ADHAR CARD PRINT PORTAL | BABA PRINT PORTAL | SHIVYOG PRINT PORTAL LOGIN | PRINT PORTAL ONLINE | PRINT PORTAL FREE | SHOBHNATH PRINT PORTAL | PRINT PORTAL NEW REGISTRATION | PRINT PORTAL FASTPRINT CARD | BEST PRINT PORTAL LOGIN | JUST CARD PRINT PORTAL | SHIV YOG PRINT PORTAL | KAYAMAT PRINT PORTAL | CARD PRINT PORTAL | CARD PRINT PORTAL | DIGITAL AADHAR PRINT PORTAL | SK PRINT PORTAL LOGIN | PRINT PORTAL CUSTOMER CARE NUMBER | PAN CARD PRINT PORTAL | UIDAI PRINT PORTAL | RK PRINT PORTAL | SK PRINT PORTAL REGISTRATION | AJIT PRINT PORTAL | DIGITAL FIRST PRINT PORTAL | BEST CARD PRINT PORTAL | KAYAMAT XYZ PRINT PORTAL | E PRINT PORTAL | AYUSHMAN CARD PRINT PORTAL | PRINT PORTAL SERVICE | SOMNATH PRINT PORTAL HELPLINE NUMBER | PP PRINT PORTAL | BEST PRINT PORTAL ONLINE | SPEED PRINT PORTAL | FREE AADHAR PRINT PORTAL | AMAR PRINT PORTAL | MG PRINT PORTAL | MANUAL AADHAAR PRINT PORTAL | FIRST PRINT PORTAL | ALL PRINT PORTAL | E-PRINT PORTAL | SONU PRINT PORTAL | PRINT PORTAL WEBSITE | AADHAR PRINT PORTAL LOGIN | AADHAR MANUAL PRINT PORTAL | PRINT PORTAL CONTACT NUMBER | CSC AADHAAR PRINT PORTAL | SMART CARD PRINT PORTAL | FAST CARD PRINT PORTAL | ADHAR PRINT PORTAL | AADHAR PRINT PORTAL FREE | AADHAR PRINT PORTAL FINGERPRINT | BHARATSEVA PRINT PORTAL | JUST PRINT PORTAL | SOMNATH PRINT PORTAL COM | AYUSHMAN PRINT PORTAL | PRINT PORTAL AADHAR CARD | PRINT PORTAL CSC | JIO PRINT PORTAL | CSC PRINT PORTAL LOGIN | PRINT PORTAL VOTER ID | Aadhaar Smart Card Online | PVC Aadhaar Card | Online PVC | Plastic Aadhaar Card | PVC Smart Card | Aadhaar Plastic Card | Aadhaar PVC Card | Smart Aadhaar Card Online | Aadhar Smart Card Online | PVC Aadhar Card | Online PVC | Plastic Aadhar Card | PVC Smart Card | Aadhar Plastic Card | Aadhar PVC Card | Smart Aadhar Card Online | Adhaar Smart Card Online | PVC Adhaar Card | Online PVC | Plastic Adhaar Card | PVC Smart Card | Adhaar Plastic Card | Adhaar PVC Card | Smart Adhaar Card Online | Adhar Smart Card Online | PVC Adhar Card | Online PVC | Plastic Adhar Card | PVC Smart Card | Adhar Plastic Card | Adhar PVC Card | Smart Adhar Card Online" />
    
    <meta name="Description" content="AADHAR BEST PRINT | PRINT PORTAL | aadhaar print portal | print portal xyz | digital fast print | balaji print portal | harshit print portal | best print portal | PRINT PORTAL | AADHAR PRINT PORTAL | AADHAAR PRINT PORTAL | SHIVYOG PRINT PORTAL | DIGITAL FAST PRINT | BALAJI PRINT PORTAL | AADHAAR PRINT PORTAL FINGERPRINT | ADVANCE AADHAAR PRINT PORTAL | SK PRINT PORTAL | RAVI PRINT PORTAL | BEST PRINT PORTAL | BEST AADHAAR PRINT PORTAL | FAST PRINT PORTAL | MY RAVI PRINT PORTAL | PRINT PORTAL AADHAR | DIGITAL PRINT PORTAL | SN PRINT PORTAL | FREE PRINT PORTAL | SUPER PVC PRINT PORTAL | VLE PRINT PORTAL | PK PRINT PORTAL | INSTANT PRINT PORTAL | MILAN PRINT PORTAL | PRINT PORTAL REGISTRATION | ONLINE PRINT PORTAL | PVC PRINT PORTAL | MY PRINT PORTAL | SHIV PRINT PORTAL | AADHAAR PRINT PORTAL FREE | AADHAAR PRINT PORTAL XYZ | AADHAR CARD PRINT PORTAL | AADHAAR PRINT PORTAL REGISTRATION | NEW PRINT PORTAL | FREE PRINT PORTAL XYZ | FREE AADHAAR PRINT PORTAL | UID PRINT PORTAL | ADHAR CARD PRINT PORTAL | BABA PRINT PORTAL | SHIVYOG PRINT PORTAL LOGIN | PRINT PORTAL ONLINE | PRINT PORTAL FREE | SHOBHNATH PRINT PORTAL | PRINT PORTAL NEW REGISTRATION | PRINT PORTAL FASTPRINT CARD | BEST PRINT PORTAL LOGIN | JUST CARD PRINT PORTAL | SHIV YOG PRINT PORTAL | KAYAMAT PRINT PORTAL | CARD PRINT PORTAL | CARD PRINT PORTAL | DIGITAL AADHAR PRINT PORTAL | SK PRINT PORTAL LOGIN | PRINT PORTAL CUSTOMER CARE NUMBER | PAN CARD PRINT PORTAL | UIDAI PRINT PORTAL | RK PRINT PORTAL | SK PRINT PORTAL REGISTRATION | AJIT PRINT PORTAL | DIGITAL FIRST PRINT PORTAL | BEST CARD PRINT PORTAL | KAYAMAT XYZ PRINT PORTAL | E PRINT PORTAL | AYUSHMAN CARD PRINT PORTAL | PRINT PORTAL SERVICE | SOMNATH PRINT PORTAL HELPLINE NUMBER | PP PRINT PORTAL | BEST PRINT PORTAL ONLINE | SPEED PRINT PORTAL | FREE AADHAR PRINT PORTAL | AMAR PRINT PORTAL | MG PRINT PORTAL | MANUAL AADHAAR PRINT PORTAL | FIRST PRINT PORTAL | ALL PRINT PORTAL | E-PRINT PORTAL | SONU PRINT PORTAL | PRINT PORTAL WEBSITE | AADHAR PRINT PORTAL LOGIN | AADHAR MANUAL PRINT PORTAL | PRINT PORTAL CONTACT NUMBER | CSC AADHAAR PRINT PORTAL | SMART CARD PRINT PORTAL | FAST CARD PRINT PORTAL | ADHAR PRINT PORTAL | AADHAR PRINT PORTAL FREE | AADHAR PRINT PORTAL FINGERPRINT | BHARATSEVA PRINT PORTAL | JUST PRINT PORTAL | SOMNATH PRINT PORTAL COM | AYUSHMAN PRINT PORTAL | PRINT PORTAL AADHAR CARD | PRINT PORTAL CSC | JIO PRINT PORTAL | CSC PRINT PORTAL LOGIN | PRINT PORTAL VOTER ID | Aadhaar Smart Card Online | PVC Aadhaar Card | Online PVC | Plastic Aadhaar Card | PVC Smart Card | Aadhaar Plastic Card | Aadhaar PVC Card | Smart Aadhaar Card Online | Aadhar Smart Card Online | PVC Aadhar Card | Online PVC | Plastic Aadhar Card | PVC Smart Card | Aadhar Plastic Card | Aadhar PVC Card | Smart Aadhar Card Online | Adhaar Smart Card Online | PVC Adhaar Card | Online PVC | Plastic Adhaar Card | PVC Smart Card | Adhaar Plastic Card | Adhaar PVC Card | Smart Adhaar Card Online | Adhar Smart Card Online | PVC Adhar Card | Online PVC | Plastic Adhar Card | PVC Smart Card | Adhar Plastic Card | Adhar PVC Card | Smart Adhar Card Online" />
    
    <meta property=og:title content="PRINT PORTAL - Print Portal | aadhar print portal | Print Portal Login | fast card login	| card registration	 | fast print online	| fast card print	| adhaar card login	 | fast print portal	| print portal login Aadhaar print Portal | Digital Fast Print | Best Card Print Online | Aadhaar Smart Card Online | PVC Aadhaar Card | Online PVC | Plastic Aadhaar Card | PVC Smart Card | Aadhaar Plastic Card | Aadhaar PVC Card | Smart Aadhaar Card Online | Aadhar Smart Card Online | PVC Aadhar Card | Online PVC | Plastic Aadhar Card | PVC Smart Card | Aadhar Plastic Card | Aadhar PVC Card | Smart Aadhar Card Online | Adhaar Smart Card Online | PVC Adhaar Card | Online PVC | Plastic Adhaar Card | PVC Smart Card | Adhaar Plastic Card | Adhaar PVC Card | Smart Adhaar Card Online | Adhar Smart Card Online | PVC Adhar Card | Online PVC | Plastic Adhar Card | PVC Smart Card | Adhar Plastic Card | Adhar PVC Card | Smart Adhar Card Online"/>
	<meta property=og:description content="PRINT PORTAL | AADHAR PRINT PORTAL | AADHAAR PRINT PORTAL | SHIVYOG PRINT PORTAL | DIGITAL FAST PRINT | BALAJI PRINT PORTAL | AADHAAR PRINT PORTAL FINGERPRINT | ADVANCE AADHAAR PRINT PORTAL | SK PRINT PORTAL | Aadhaar Smart Card Online | PVC Aadhaar Card | Online PVC | Plastic Aadhaar Card | PVC Smart Card | Aadhaar Plastic Card | Aadhaar PVC Card | Smart Aadhaar Card Online | Aadhar Smart Card Online | PVC Aadhar Card | Online PVC | Plastic Aadhar Card | PVC Smart Card | Aadhar Plastic Card | Aadhar PVC Card | Smart Aadhar Card Online | Adhaar Smart Card Online | PVC Adhaar Card | Online PVC | Plastic Adhaar Card | PVC Smart Card | Adhaar Plastic Card | Adhaar PVC Card | Smart Adhaar Card Online | Adhar Smart Card Online | PVC Adhar Card | Online PVC | Plastic Adhar Card | PVC Smart Card | Adhar Plastic Card | Adhar PVC Card | Smart Adhar Card Online"/>
    <meta property="og:type" content="website">
    <!-- Include All CSS -->
    <meta name="copyright" content="Print Portal, https://flex4upoint.site/">
    <meta name="robots" content="index, follow">
    <meta name="Author" content=", Private Limited">
    <meta property=og:url content="https://flex4upoint.site/">
     <link rel="icon" href="/logof.png" type="image/png">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Bootstrap + Icons -->
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">

  <style>
    body {
      margin: 0;
      padding: 0;
      background: linear-gradient(135deg, #14772a, #6470a7);
      min-height: 100vh;
      font-family: 'Segoe UI', sans-serif;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .glass-card {
      background: rgba(255, 255, 255, 0.15);
      border-radius: 20px;
      backdrop-filter: blur(12px);
      box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
      padding: 40px;
      width: 100%;
      max-width: 600px;
      color: #fff;
    }

    .glass-card h2 {
      font-weight: bold;
      text-align: center;
      margin-bottom: 10px;
    }

    .form-control {
      background: rgba(255, 255, 255, 0.2);
      border: none;
      color: #fff;
      border-radius: 12px;
      padding-left: 40px;
    }

    .form-control::placeholder {
      color: #eee;
    }

    .input-icon {
      position: relative;
    }

    .input-icon i {
      position: absolute;
      top: 50%;
      left: 12px;
      transform: translateY(-50%);
      color: #ddd;
    }

    .btn-primary {
      width: 100%;
      padding: 12px;
      border-radius: 50px;
      font-weight: 500;
      background: #ffffff;
      color: #333;
      border: none;
      transition: 0.3s ease;
    }

    .btn-primary:hover {
      background: #f0f0f0;
      color: #000;
    }

    .alert {
      border-radius: 12px;
    }

    .back-button {
      display: inline-block;
      margin-top: 15px;
      text-align: center;
      color: #fff;
      background: rgba(255, 255, 255, 0.25);
      border-radius: 8px;
      padding: 10px 20px;
      text-decoration: none;
      transition: 0.3s;
    }

    .back-button:hover {
      background: rgba(255, 255, 255, 0.4);
      color: #000;
    }
  </style>
</head>

<body>
  <div class="glass-card">
    <h2>📬 Contact Us</h2>
    <p class="text-center mb-4">We'd love to hear from you!</p>

    <?php if ($messageSent): ?>
      <div class="alert alert-success">✅ Thank you! Your message has been sent.</div>
    <?php elseif ($error): ?>
      <div class="alert alert-danger">❌ <?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <div class="mb-3 input-icon">
        <i class="bi bi-person-fill"></i>
        <input type="text" name="name" class="form-control" placeholder="Your Name" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
      </div>
      <div class="mb-3 input-icon">
        <i class="bi bi-envelope-fill"></i>
        <input type="email" name="email" class="form-control" placeholder="Your Email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
      </div>
      <div class="mb-3 input-icon">
        <i class="bi bi-chat-right-dots-fill"></i>
        <input type="text" name="subject" class="form-control" placeholder="Subject" required value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>">
      </div>
      <div class="mb-4 input-icon">
        <i class="bi bi-pencil-fill"></i>
        <textarea name="message" rows="5" class="form-control" placeholder="Your Message..." required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
      </div>
      <button type="submit" name="send" class="btn btn-primary">Send Message</button>
    </form>

    <div class="text-center">
      <a href="javascript:history.back()" class="back-button mt-4">🔙 Go Back</a>
    </div>
  </div>

  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
