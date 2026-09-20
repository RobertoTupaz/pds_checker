<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 190px 70px 130px 70px; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #111827; font-size: 13px; }

        .letterhead { position: fixed; top: -165px; left: 0; right: 0; text-align: center; font-family: 'DejaVu Serif', serif; }
        .letterhead img { width: 76px; height: auto; }
        .letterhead .republic { font-size: 13px; margin-top: 4px; }
        .letterhead .agency { font-size: 20px; font-weight: bold; }
        .letterhead .region { font-size: 10px; font-weight: bold; margin-top: 2px; }
        .letterhead .division { font-size: 10px; font-weight: bold; padding-bottom: 2px; border-bottom: 1px solid #000; }

        .footer { position: fixed; bottom: -105px; left: 0; right: 0; border-top: 2px solid #000; padding-top: 8px; }
        .footer table { border-collapse: collapse; }
        .footer td { vertical-align: middle; padding-right: 10px; }
        .footer .contact { font-family: 'DejaVu Sans', sans-serif; font-size: 7px; line-height: 1.5; }

        .title { text-align: center; font-size: 20px; font-weight: bold; letter-spacing: 2px; margin: 30px 0 32px 0; }
        .center { text-align: center; }
        .name { text-align: center; font-size: 19px; font-weight: bold; margin: 18px 0; }
        .name span { border-bottom: 1px solid #111827; padding: 0 18px 3px 18px; }
        .body-text { line-height: 1.9; text-align: justify; margin: 0 20px 26px 20px; }
        .issued { text-align: center; margin: 34px 0 44px 0; }
        .disclaimer { font-size: 9px; font-style: italic; color: #4b5563; text-align: justify; margin: 0 20px; }
    </style>
</head>
<body>
    <div class="letterhead">
        @if ($letterhead['seal'] !== '')
            <img src="{{ $letterhead['seal'] }}" alt="">
        @endif
        @foreach ($letterhead['headerLines'] as $index => $line)
            <div class="{{ ['republic', 'agency', 'region', 'division'][$index] ?? 'region' }}">{{ $line }}</div>
        @endforeach
    </div>

    <div class="footer">
        <table>
            <tr>
                @foreach ($letterhead['footerLogos'] as $index => $logo)
                    <td><img src="{{ $logo }}" alt="" style="height: {{ $index === 0 ? '46px' : '42px' }}; width: auto;"></td>
                @endforeach
                <td class="contact">
                    @foreach ($letterhead['footerLines'] as $line)
                        {{ $line }}<br>
                    @endforeach
                </td>
            </tr>
        </table>
    </div>

    <div class="title">CERTIFICATION OF COMPLETENESS</div>

    <div class="center">This is to certify that the Personal Data Sheet (CS Form No. 212, Revised 2026) submitted by</div>

    <div class="name"><span>{{ mb_strtoupper($applicantName) }}</span></div>

    <div class="body-text">
        has been checked by PDS Checker and found to be completely and consistently filled out, based on the
        required-field, format, and logical-consistency checks performed on Sections I to VIII of the form
        (Personal Information, Family Background, Educational Background, Civil Service Eligibility, Work
        Experience, Voluntary Work, Learning and Development, and Other Information).
    </div>

    <div class="issued">Issued on {{ $issuedAt->format('F j, Y') }}.</div>

    <div class="disclaimer">
        This certification confirms that the entries are complete and internally consistent according to
        automated checks. It does not verify the truthfulness or accuracy of the information provided, which
        remains the sole responsibility of the person who accomplished the form.
    </div>
</body>
</html>
