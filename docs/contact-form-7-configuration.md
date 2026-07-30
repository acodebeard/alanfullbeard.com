# Contact form configuration

The contact page uses these WordPress.org plugins:

- Contact Form 7
- Flamingo
- AntiSpam for Contact Form 7

The form title must be `Website contact` because the theme renders it by title.

## Form

```text
<div class="contact-form__field">
<label for="contact-name">Name <span class="contact-form__required">Required</span></label>
[text* your-name id:contact-name maxlength:100 autocomplete:name]
</div>

<div class="contact-form__field">
<label for="contact-email">Email <span class="contact-form__required">Required</span></label>
[email* your-email id:contact-email maxlength:254 autocomplete:email]
</div>

<div class="contact-form__field">
<label for="contact-phone">Phone <span class="contact-form__required">Optional</span></label>
[tel your-phone id:contact-phone maxlength:30 autocomplete:tel]
</div>

<fieldset class="contact-form__field contact-form__field--choice" aria-describedby="contact-text-permission-help">
<legend>Permission to text</legend>
<p class="contact-form__field-help" id="contact-text-permission-help">Can Alan text you at this number? Carrier charges may apply.</p>
[radio your-text-permission class:contact-form__choices use_label_element default:1 "No, please don't text me|No" "Yes, you can text me|Yes"]
</fieldset>

<div class="contact-form__field">
<label for="contact-message">Message <span class="contact-form__required">Required</span></label>
[textarea* your-message id:contact-message maxlength:5000 40x8]
</div>

<div class="contact-form__footer">
<p id="contact-form-privacy">Submitting this form privately stores an encrypted copy in WordPress for spam review and follow-up for up to 180 days. The site may also attempt to send Alan an email notification. See the <a href="/privacy-policy/">Privacy Policy</a>.</p>
<div class="contact-form__actions">
<div class="contact-form__turnstile" role="group" aria-label="Security verification">[turnstile action:contact appearance:interaction-only size:compact theme:dark]</div>
<div class="contact-form__submit">[submit "Send message"]</div>
</div>
</div>
```

## Mail

- To: `alanjfullmer@gmail.com`
- From: `[_site_title] <alan@alanfullbeard.com>`
- Subject: `Website contact from [your-name]`
- Additional headers: `Reply-To: [your-name] <[your-email]>`

```text
Name: [your-name]
Email: [your-email]
Phone: [your-phone]
Permission to text: [your-text-permission]

Message:
[your-message]

--
Submitted from [_site_title] ([_site_url])
```

## Additional settings

```text
flamingo_email: "[your-email]"
flamingo_name: "[your-name]"
flamingo_subject: "Website contact from [your-name]"
flamingo_message: "[your-message]"
```

The `flamingo_message` marker enables B8 Bayesian analysis. Contact Form 7
receives the existing Turnstile credentials through the MailerSend plugin
filters, so the keys do not need to be duplicated in Contact Form 7.

Flamingo automatically stores each submission for spam review and follow-up;
there is no separate storage opt-in. The Encrypted Contact Vault mu-plugin runs
after spam scoring and replaces all visitor fields, sender details, technical
metadata, consent data, and spam context with one authenticated Sodium
XChaCha20-Poly1305 payload before Flamingo writes the record. It suppresses
Flamingo's separate address-book copy so the email address is not duplicated in
plaintext.

Only the date, delivery or spam status, spam score, form ID, encryption version,
and a keyed exact-match privacy lookup remain readable without the vault key.
Email is a best-effort notification rather than the only copy; a delivered
email is outside the database vault. The retention mu-plugin automatically
deletes ordinary records after 180 days and spam or trash records after 30 days.
