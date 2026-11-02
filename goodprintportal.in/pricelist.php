<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digital Services Price List</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            color: white;
            margin-bottom: 30px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            backdrop-filter: blur(10px);
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
        }
        
        .service-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .service-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.25);
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .card-icon {
            font-size: 2.5rem;
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .card-title {
            font-size: 1.4rem;
            font-weight: 600;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .service-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.2s ease;
        }
        
        .service-item:last-child {
            border-bottom: none;
        }
        
        .service-item:hover {
            background: #f8f9fa;
        }
        
        .service-name {
            font-weight: 500;
            color: #333;
        }
        
        .service-price {
            font-weight: 700;
            color: #27ae60;
            font-size: 1.1rem;
        }
        
        .special-badge {
            background: #f39c12;
            color: white;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            margin-left: 8px;
        }
        
        .special-card .card-header {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
        }
        
        @media (max-width: 768px) {
            .services-grid {
                grid-template-columns: 1fr;
            }
            
            .header h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🌐 ALL DIGITAL SERVICES PRICE LIST (UPDATED)</h1>
            <p>Complete digital services with modern UI design</p>
        </div>
        
        <div class="services-grid">
            <!-- Aadhaar Services Card -->
            <div class="service-card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-id-card"></i>
                    </div>
                    <div class="card-title">💳 Aadhaar Services</div>
                </div>
                <div class="card-body">
                    <div class="service-item">
                        <span class="service-name">Aadhaar e-KYC</span>
                        <span class="service-price">₹20</span>
                    </div>
                    <div class="service-item">
                        <span class="service-name">Aadhaar Manual</span>
                        <span class="service-price">₹10</span>
                    </div>
                    <div class="service-item">
                        <span class="service-name">Aadhaar PDF Download</span>
                        <span class="service-price">₹50</span>
                    </div>
                    <div class="service-item">
                        <span class="service-name">Aadhaar Verify</span>
                        <span class="service-price">₹2</span>
                    </div>
                    <div class="service-item">
                        <span class="service-name">Aadhaar Number Find</span>
                        <span class="service-price">₹300</span>
                    </div>
                    <div class="service-item">
                        <span class="service-name">Generated EID</span>
                        <span class="service-price">₹20</span>
                    </div>
                    <div class="service-item">
                        <span class="service-name">Enrollment Status</span>
                        <span class="service-price">₹2</span>
                    </div>
                </div>
            </div>
            
            <!-- PAN Services Card -->
            <div class="service-card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <div class="card-title">🪪 PAN Services</div>
                </div>
                <div class="card-body">
                    <div class="service-item">
                        <span class="service-name">PAN Details Verify</span>
                        <span class="service-price">₹10</span>
                    </div>
                    <div class="service-item">
                        <span class="service-name">Instant PAN Manual</span>
                        <span class="service-price">₹10</span>
                    </div>
                    <div class="service-item">
                        <span class="service-name">PAN Apply</span>
                        <span class="service-price">₹130</span>
                    </div>
                    <div class="service-item">
                        <span class="service-name">New PAN Request</span>
                        <span class="service-price">₹120</span>
                    </div>
                    <div class="service-item">
                        <span class="service-name">PAN Aadhaar Find</span>
                        <span class="service-price">₹200</span>
                    </div>
                    <div class="service-item">
                        <span class="service-name">Aadhaar to PAN Find</span>
                        <span class="service-price">₹20</span>
                    </div>
                    <div class="service-item">
                        <span class="service-name">PAN Aadhaar Status</span>
                        <span class="service-price">₹2</span>
                    </div>
                    <div class="service-item">
                        <span class="service-name">PAN Aadhaar Mask</span>
                        <span class="service-price">₹2</span>
                    </div>
                </div>
            </div>
            
            <!-- Voter Services Card -->
            <div class="service-card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-vote-yea"></i>
                    </div>
                    <div class="card-title">🗳️ Voter Services</div>
                </div>
                <div class="card-body">
                    <div class="service-item">
                        <span class="service-name">Voter Manual</span>
                        <span class="service-price">₹10</span>
                    </div>
                    <div class="service-item">
                        <span class="service-name">Voter Mobile Link</span>
                        <span class="service-price">₹30</span>
                    </div>
                    <div class="service-item">
                        <span class="service-name">Voter PDF Instant</span>
                        <span class="service-price">₹15</span>
                    </div>
                    <div class="service-item">
                        <span class="service-name">Voter Original</span>
                        <span class="service-price">₹20</span>
                    </div>
                </div>
            </div>
            
            <!-- Vehicle & Driving Services Card -->
            <div class="service-card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-car"></i>
                    </div>
                    <div class="card-title">🚗 Vehicle & Driving Services</div>
                </div>
                <div class="card-body">
                    <div class="service-item">
                        <span class="service-name">RC Vehicle Details</span>
                        <span class="service-price">₹20</span>
                    </div>
                    <div class="service-item">
                        <span class="service-name">Challan Check</span>
                        <span class="service-price">₹10</span>
                    </div>
                    <div class="service-item">
                        <span class="service-name">Driving Licence PDF</span>
                        <span class="service-price">₹20</span>
                    </div>
                    <div class="service-item">
                        <span class="service-name">Driving Licence Find</span>
                        <span class="service-price">₹10</span>
                    </div>
                    <div class="service-item">
                        <span class="service-name">LL Request</span>
                        <span class="service-price">₹100</span>
                    </div>
                    <div class="service-item">
                        <span class="service-name">Bike (2 Wheeler)</span>
                        <span class="service-price">₹120</span>
                    </div>
                    <div class="service-item">
                        <span class="service-name">Petrol/CNG Vehicle</span>
                        <span class="service-price">₹180</span>
                    </div>
                    <div class="service-item">
                        <span class="service-name">Diesel Vehicle</span>
                        <span class="service-price">₹230</span>
                    </div>
                    <div class="service-item">
                        <span class="service-name">Normal PUC</span>
                        <span class="service-price">₹100</span>
                    </div>
                    <div class="service-item">
                        <span class="service-name">Chassis RC Find</span>
                        <span class="service-price">₹30</span>
                    </div>
                </div>
            </div>
            
            <!-- Ration Services Card -->
            <div class="service-card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-shopping-basket"></i>
                    </div>
                    <div class="card-title">🍚 Ration Services</div>
                </div>
                <div class="card-body">
                    <div class="service-item">
                        <span class="service-name">Aadhaar to Ration Details</span>
                        <span class="service-price">₹10</span>
                    </div>
                    <div class="service-item">
                        <span class="service-name">Ration Aadhaar Link</span>
                        <span class="service-price">₹300</span>
                    </div>
                    <div class="service-item">
                        <span class="service-name">Ration Jharkhand Link</span>
                        <span class="service-price">₹50</span>
                    </div>
                    <div class="service-item">
                        <span class="service-name">Ration UID Find</span>
                        <span class="service-price">₹100</span>
                    </div>
                    <div class="service-item">
                        <span class="service-name">Ration PDF Print</span>
                        <span class="service-price">₹20</span>
                    </div>
                    <div class="service-item">
                        <span class="service-name">Ration to Aadhaar Find All</span>
                        <span class="service-price">₹170</span>
                    </div>
                </div>
            </div>
            
            <!-- Health & Govt Services Card -->
            <div class="service-card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-heartbeat"></i>
                    </div>
                    <div class="card-title">🏥 Health & Govt Services</div>
                </div>
                <div class="card-body">
                    <div class="service-item">
                        <span class="service-name">Ayushman Card</span>
                        <span class="service-price">₹40</span>
                    </div>
                    <div class="service-item">
                        <span class="service-name">eShram Card</span>
                        <span class="service-price">₹50</span>
                    </div>
                    <div class="service-item">
                        <span class="service-name">eShram Details Find</span>
                        <span class="service-price">₹120</span>
                    </div>
                    <div class="service-item">
                        <span class="service-name">eShram PDF</span>
                        <span class="service-price">₹120</span>
                    </div>
                    <div class="service-item">
                        <span class="service-name">Farmer DBT</span>
                        <span class="service-price">₹20</span>
                    </div>
                    <div class="service-item">
                        <span class="service-name">Agri Stack</span>
                        <span class="service-price">₹5</span>
                    </div>
                    <div class="service-item">
                        <span class="service-name">Agri Farmer PDF</span>
                        <span class="service-price">₹20</span>
                    </div>
                </div>
            </div>
            
            <!-- Banking & Utility Services Card -->
            <div class="service-card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-university"></i>
                    </div>
                    <div class="card-title">🏦 Banking & Utility Services</div>
                </div>
                <div class="card-body">
                    <div class="service-item">
                        <span class="service-name">IFSC Verification</span>
                        <span class="service-price">₹2</span>
                    </div>
                    <div class="service-item">
                        <span class="service-name">NPCI Status Check</span>
                        <span class="service-price">₹20</span>
                    </div>
                    <div class="service-item">
                        <span class="service-name">Mobile Operator Check</span>
                        <span class="service-price">₹2</span>
                    </div>
                    <div class="service-item">
                        <span class="service-name">PIN Code Check</span>
                        <span class="service-price">₹2</span>
                    </div>
                    <div class="service-item">
                        <span class="service-name">Electricity Bill Details</span>
                        <span class="service-price">₹5</span>
                    </div>
                    <div class="service-item">
                        <span class="service-name">Electricity Bill PDF</span>
                        <span class="service-price">₹10</span>
                    </div>
                </div>
            </div>
            
            <!-- Other Services Card -->
            <div class="service-card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-cogs"></i>
                    </div>
                    <div class="card-title">📱 Other Services</div>
                </div>
                <div class="card-body">
                    <div class="service-item">
                        <span class="service-name">Airtel Password</span>
                        <span class="service-price">₹10</span>
                    </div>
                    <div class="service-item">
                        <span class="service-name">Fino Password</span>
                        <span class="service-price">₹10</span>
                    </div>
                    <div class="service-item">
                        <span class="service-name">IPPB Password</span>
                        <span class="service-price">₹10</span>
                    </div>
                    <div class="service-item">
                        <span class="service-name">PVC Convert</span>
                        <span class="service-price">₹10</span>
                    </div>
                    <div class="service-item">
                        <span class="service-name">GST Verification</span>
                        <span class="service-price">₹6</span>
                    </div>
                    <div class="service-item">
                        <span class="service-name">Bihar RTPS All</span>
                        <span class="service-price">₹6</span>
                    </div>
                    <div class="service-item">
                        <span class="service-name">Student Card</span>
                        <span class="service-price">₹10</span>
                    </div>
                    <div class="service-item">
                        <span class="service-name">Haryana Family ID</span>
                        <span class="service-price">₹30</span>
                    </div>
                    <div class="service-item">
                        <span class="service-name">TEC Certificate</span>
                        <span class="service-price">₹500</span>
                    </div>
                </div>
            </div>
            
            <!-- Special Services Card -->
            <div class="service-card special-card">
                <div class="card-header">
                    <div class="card-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="card-title">⚡ SPECIAL SERVICES</div>
                </div>
                <div class="card-body">
                    <div class="service-item">
                        <span class="service-name">V Mobile Link</span>
                        <span class="service-price">₹30</span>
                    </div>
                    <div class="service-item">
                        <span class="service-name">Aadhaar to Ration All Data</span>
                        <span class="service-price">₹170</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>