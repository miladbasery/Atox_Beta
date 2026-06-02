<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>به زودی...</title>
    <style>
        
		@font-face {
			font-family: 'MyCustomFont';
			src: url('fonts/font.ttf') format('truetype');
			font-weight: normal;
			font-style: normal;
			font-display: swap;
		}

        body, html {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100vh;
            font-family: 'CustomFont', Tahoma, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            
            background: linear-gradient(45deg, #1A2980, #26D0CE, #134E5E);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
            overflow: hidden;
            position: relative;
        }

        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        
        .glow-shape {
            position: absolute;
            border-radius: 50%;
            filter: blur(60px);
            z-index: 0;
            animation: float 8s ease-in-out infinite;
        }

        
        .glow-shape:nth-child(1) {
            width: 350px;
            height: 350px;
            top: -100px;
            left: -100px;
            background: linear-gradient(45deg, #FFD700, #FF8C00);
        }

        
        .glow-shape:nth-child(2) {
            width: 300px;
            height: 300px;
            bottom: -50px;
            right: -50px;
            background: linear-gradient(45deg, #00c6ff, #0072ff);
            animation-delay: -4s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-30px) scale(1.1); }
        }

        
        .glass-btn {
            position: relative;
            z-index: 1;
            padding: 35px 80px;
            text-align: center;
            color: #ffffff;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 40px;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.3);
            text-shadow: 0 4px 10px rgba(0, 0, 0, 0.4);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            cursor: pointer;
            user-select: none;
        }

        .glass-btn .title {
            font-size: 3.5rem;
            font-weight: bold;
            display: block;
            margin-bottom: 10px;
        }

        .glass-btn .subtitle {
            font-size: 1.2rem;
            opacity: 0.85;
            letter-spacing: 0.5px;
        }

        .glass-btn:hover {
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.4);
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 15px 40px 0 rgba(0, 0, 0, 0.4);
        }

        .glass-btn:active {
            transform: translateY(2px) scale(0.98);
            box-shadow: 0 5px 15px 0 rgba(0, 0, 0, 0.3);
        }

        
        @media (max-width: 768px) {
            .glass-btn {
                padding: 25px 50px;
                border-radius: 30px;
            }
            .glass-btn .title { font-size: 2.5rem; }
            .glass-btn .subtitle { font-size: 1rem; }
            .glow-shape:nth-child(1) { width: 250px; height: 250px; }
            .glow-shape:nth-child(2) { width: 200px; height: 200px; }
        }

        @media (max-width: 480px) {
            .glass-btn {
                padding: 20px 30px;
                border-radius: 20px;
            }
            .glass-btn .title { font-size: 2rem; }
            .glass-btn .subtitle { font-size: 0.9rem; }
        }
    </style>
</head>
<body>

    <div class="glow-shape"></div>
    <div class="glow-shape"></div>

    <div class="glass-btn">
        <span class="title">به زودی...</span>
        <span class="subtitle">خبرهای بسیار خوبی در راه است ✨</span>
    </div>

</body>
</html>
