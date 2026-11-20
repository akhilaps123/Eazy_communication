<x-mail::message>
# Your eSIM is ready

Hello {{ $name }},

Good news — the eSIM for your order **{{ $orderNumber }}** is ready to install.

<x-mail::table>
| eSIM | |
|:-----|--:|
@foreach ($esims as $esim)
| {{ $esim['bundle'] }} | [View eSIM]({{ $esim['url'] }}) |
@endforeach
</x-mail::table>

<x-mail::button :url="$esimsUrl">
View my eSIMs
</x-mail::button>

**How to install:** sign in to your Eazy Communication account, open **My eSIMs** and select your eSIM. On that page you will find the QR code to scan from your phone’s settings (Settings → Mobile/Cellular → Add eSIM), step-by-step instructions for iPhone and Android, and the details for manual installation.

For your security, the QR code and activation details are not included in this email — they are only shown after you sign in.

Your device must support eSIM. Installing the eSIM does not necessarily activate the plan straight away; activation depends on the plan and the network.

You can also view your order at any time: [Order {{ $orderNumber }}]({{ $orderUrl }}). Need help? Visit our [eSIM support page]({{ $supportUrl }}).

Thanks,<br>
Eazy Communication
</x-mail::message>
