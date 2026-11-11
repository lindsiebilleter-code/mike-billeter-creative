// Site-wide configuration. Edit here; nothing else needs to change.
window.MBW_SITE = {
  name: 'Mike Billeter Creative',
  url: 'https://mikebilleter.com',
  email: 'mike.billeter@gmail.com',
  subjectPrefix: '[Website Inquiry]',
  // Form delivery. The site's own PHP backend (forms/submit.php) sends through Resend;
  // recipients, subject, reCAPTCHA and spam settings live at /admin/forms/, not here.
  // configUrl supplies the public reCAPTCHA site key and timing from those settings.
  // Leave endpoint empty and the form says so honestly and offers a pre-filled mailto link.
  form: { endpoint: '/forms/submit.php', configUrl: '/forms/config.php', accessKey: '', minSeconds: 3 },
};
