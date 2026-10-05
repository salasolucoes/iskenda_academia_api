<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Inter:wght@300;400;500;600&display=swap');

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            width: 1122px;
            height: 794px;
            background: #ffffff;
            color: #1a1a2e;
        }

        .certificate {
            width: 100%;
            height: 100%;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 60px;
        }

        .border-frame {
            position: absolute;
            top: 20px; left: 20px; right: 20px; bottom: 20px;
            border: 3px solid #1a365d;
            border-radius: 12px;
        }

        .border-inner {
            position: absolute;
            top: 30px; left: 30px; right: 30px; bottom: 30px;
            border: 1px solid #c4a35a;
            border-radius: 8px;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            z-index: 1;
        }

        .header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 36px;
            font-weight: 700;
            color: #1a365d;
            letter-spacing: 4px;
            text-transform: uppercase;
        }

        .header .subtitle {
            font-size: 13px;
            color: #c4a35a;
            letter-spacing: 6px;
            text-transform: uppercase;
            margin-top: 6px;
            font-weight: 500;
        }

        .body {
            text-align: center;
            z-index: 1;
        }

        .body p {
            font-size: 14px;
            color: #4a5568;
            margin-bottom: 12px;
            font-weight: 300;
        }

        .body .student-name {
            font-family: 'Playfair Display', serif;
            font-size: 30px;
            font-weight: 700;
            color: #1a365d;
            margin: 10px 0 18px;
            border-bottom: 2px solid #c4a35a;
            display: inline-block;
            padding-bottom: 6px;
        }

        .body .course-title {
            font-size: 16px;
            color: #2d3748;
            font-weight: 600;
            margin: 4px 0 20px;
        }

        .footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            width: 70%;
            z-index: 1;
            margin-top: 30px;
        }

        .footer .date {
            text-align: center;
            font-size: 12px;
            color: #4a5568;
        }

        .footer .verification {
            text-align: center;
            font-size: 10px;
            color: #718096;
            max-width: 200px;
            word-break: break-all;
        }

        .footer .verification span {
            display: block;
            font-family: monospace;
            font-size: 9px;
            color: #a0aec0;
            margin-top: 4px;
        }

        .stamp {
            position: absolute;
            bottom: 50px;
            right: 60px;
            width: 80px;
            height: 80px;
            border: 2px solid #c4a35a;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0.4;
        }

        .stamp p {
            font-size: 8px;
            color: #c4a35a;
            text-align: center;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
    </style>
</head>
<body>
    <div class="certificate">
        <div class="border-frame"></div>
        <div class="border-inner"></div>

        <div class="header">
            <h1>Certificado</h1>
            <div class="subtitle">Iskenda Academy</div>
        </div>

        <div class="body">
            <p>Certificamos que</p>
            <div class="student-name">{{ $studentName }}</div>
            <p>concluiu com êxito o curso</p>
            <div class="course-title">{{ $courseTitle }}</div>
            <p>emitido em {{ $issuedAt }}</p>
        </div>

        <div class="footer">
            <div class="date">
                <div style="border-bottom: 1px solid #c4a35a; padding-bottom: 4px; margin-bottom: 4px;">Data de Emissão</div>
                {{ $issuedAt }}
            </div>
            <div class="verification">
                Verificação
                <span>{{ $verificationHash }}</span>
            </div>
        </div>

        <div class="stamp">
            <p>Iskenda<br>Academy</p>
        </div>
    </div>
</body>
</html>
