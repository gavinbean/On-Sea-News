# Email Deliverability Guide - Preventing Emails from Going to Spam

This guide will help you improve email deliverability and prevent emails from being marked as spam.

## Critical Steps (Must Do)

### 1. Set Up SPF Record (Sender Policy Framework)

SPF tells email servers which servers are authorized to send emails from your domain.

**Add this TXT record to your DNS:**
```
Type: TXT
Name: @ (or your domain name)
Value: v=spf1 a mx ip4:YOUR_SERVER_IP ~all
```

**For onseanews.co.za, if your server IP is known:**
```
v=spf1 a mx ip4:YOUR_SERVER_IP ~all
```

**If you're using a shared hosting provider, check their documentation for the correct SPF record.**

**To verify SPF is working:**
- Use: https://mxtoolbox.com/spf.aspx
- Enter: onseanews.co.za
- Should show: "SPF Record Found"

### 2. Set Up DKIM (DomainKeys Identified Mail)

DKIM adds a digital signature to your emails to prove they came from your domain.

**Contact your hosting provider** - they need to:
1. Generate DKIM keys for your domain
2. Provide you with the public key
3. Add it to your DNS as a TXT record

**DKIM DNS Record Format:**
```
Type: TXT
Name: default._domainkey (or selector._domainkey)
Value: v=DKIM1; k=rsa; p=YOUR_PUBLIC_KEY_HERE
```

**To verify DKIM:**
- Use: https://mxtoolbox.com/dkim.aspx
- Enter: onseanews.co.za

### 3. Set Up DMARC (Domain-based Message Authentication)

DMARC tells receiving servers what to do with emails that fail SPF or DKIM.

**Add this TXT record to your DNS:**
```
Type: TXT
Name: _dmarc
Value: v=DMARC1; p=none; rua=mailto:admin@onseanews.co.za; ruf=mailto:admin@onseanews.co.za; pct=100
```

**Start with `p=none` (monitoring only), then after a few weeks:**
```
v=DMARC1; p=quarantine; rua=mailto:admin@onseanews.co.za; ruf=mailto:admin@onseanews.co.za; pct=100
```

**After monitoring shows good results, use:**
```
v=DMARC1; p=reject; rua=mailto:admin@onseanews.co.za; ruf=mailto:admin@onseanews.co.za; pct=100
```

**To verify DMARC:**
- Use: https://mxtoolbox.com/dmarc.aspx
- Enter: onseanews.co.za

### 4. Reverse DNS (PTR Record)

Your server's IP address should have a reverse DNS record pointing to your domain.

**Contact your hosting provider** to set up reverse DNS for your server IP:
```
IP: YOUR_SERVER_IP
PTR Record: mail.onseanews.co.za (or your server hostname)
```

**To verify:**
- Use: https://mxtoolbox.com/ReverseLookup.aspx
- Enter your server IP

## Email Content Best Practices

### ✅ DO:
- Use a consistent "From" address (e.g., noreply@onseanews.co.za)
- Include a clear subject line
- Provide an unsubscribe link
- Use proper HTML structure
- Include both HTML and plain text versions (optional but recommended)
- Keep email size reasonable (< 100KB)
- Avoid spam trigger words (FREE, CLICK HERE, URGENT, etc.)
- Use proper grammar and spelling

### ❌ DON'T:
- Use all caps in subject lines
- Use excessive exclamation marks!!!
- Include too many links
- Use URL shorteners (bit.ly, etc.)
- Include attachments (unless necessary)
- Use spam trigger words excessively
- Send from a "no-reply" address that users can't reply to (use noreply@ but allow replies)

## Code Improvements Already Made

The email function has been updated with:
- ✅ Proper Message-ID headers
- ✅ Date headers (RFC 5322 compliant)
- ✅ List-Unsubscribe headers
- ✅ Better X-Mailer identification
- ✅ Proper MIME encoding

## Testing Your Email Deliverability

### 1. Use Email Testing Tools:
- **Mail-Tester.com**: Send an email to their test address, get a score
- **MXToolbox**: Check SPF, DKIM, DMARC, blacklists
- **GlockApps**: Comprehensive email testing

### 2. Test with Real Accounts:
- Send test emails to Gmail, Outlook, Yahoo
- Check if they land in inbox or spam
- Check email headers (View → Show Original in Gmail)

### 3. Monitor Bounce Rates:
- Track hard bounces (invalid addresses)
- Track soft bounces (temporary issues)
- Remove invalid addresses from your list

## Alternative: Use a Transactional Email Service

If DNS configuration is difficult, consider using a transactional email service:

### Recommended Services:
1. **SendGrid** (Free tier: 100 emails/day)
2. **Mailgun** (Free tier: 5,000 emails/month)
3. **Amazon SES** (Very cheap, pay per email)
4. **Postmark** (Great deliverability, paid)

**Benefits:**
- Better deliverability rates
- Built-in SPF/DKIM/DMARC
- Email analytics
- Bounce handling
- Easy to implement

## Quick Checklist

- [ ] SPF record added to DNS
- [ ] DKIM keys generated and added to DNS
- [ ] DMARC policy added to DNS
- [ ] Reverse DNS (PTR) record configured
- [ ] Email headers updated (✅ Done in code)
- [ ] Tested with Mail-Tester.com
- [ ] Tested with Gmail/Outlook/Yahoo
- [ ] Monitoring bounce rates
- [ ] Consistent "From" address
- [ ] Unsubscribe links in emails

## Need Help?

1. **Contact your hosting provider** - They can help with DNS records
2. **Check your domain registrar** - DNS records are usually managed there
3. **Use online tools** - MXToolbox, Mail-Tester.com for diagnostics

## Current Email Configuration

Your emails are currently sent from:
- **From Address**: Defined in `config/config.php` (EMAIL_FROM_ADDRESS)
- **From Name**: Defined in `config/config.php` (EMAIL_FROM_NAME)
- **Domain**: onseanews.co.za

Make sure all DNS records use the correct domain!
