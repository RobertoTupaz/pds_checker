<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            color: #1f2937;
            padding: 60px;
        }
        .border {
            border: 2px solid #1f2937;
            padding: 50px;
            text-align: center;
        }
        .title {
            font-size: 22px;
            font-weight: bold;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 30px;
        }
        .body-text {
            font-size: 13px;
            line-height: 1.8;
            margin: 0 30px 30px 30px;
        }
        .applicant-name {
            font-size: 20px;
            font-weight: bold;
            margin: 20px 0;
            border-bottom: 1px solid #1f2937;
            display: inline-block;
            padding: 0 20px 4px 20px;
        }
        .meta {
            margin-top: 50px;
            font-size: 11px;
            color: #4b5563;
        }
        .disclaimer {
            margin-top: 40px;
            font-size: 9px;
            color: #6b7280;
            text-align: left;
        }
    </style>
</head>
<body>
    <div class="border">
        <div class="title">Certification of Completeness</div>

        <div class="body-text">This is to certify that the Personal Data Sheet (CS Form No. 212) submitted by</div>

        <div class="applicant-name">{{ $applicantName }}</div>

        <div class="body-text">
            has been checked by PDS Checker and found to be completely and consistently filled out,
            based on the required-field, format, and logical-consistency checks performed on Sections
            I&ndash;V of the form (Personal Information, Family Background, Educational Background,
            Civil Service Eligibility, and Work Experience).
        </div>

        <div class="meta">Generated on {{ $generatedAt->format('F j, Y \a\t g:i A') }}</div>

        <div class="disclaimer">
            This certification confirms that the submitted entries are complete and internally
            consistent according to automated checks. It does not verify the truthfulness or
            accuracy of the information provided, which remains the sole responsibility of the
            person who accomplished the form.
        </div>
    </div>
</body>
</html>
