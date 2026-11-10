// Site-wide configuration. Edit here; nothing else needs to change.
window.MBW_SITE = {
  name: 'Mike Billeter Writing',
  url: 'https://www.mikebilleterwriting.com',
  email: 'mike.billeter@gmail.com',
  subjectPrefix: '[Website Inquiry]',
  // Form delivery. Leave endpoint empty and the form says so honestly and offers a pre-filled mailto link.
  //   formspree  → endpoint: 'https://formspree.io/f/<id>'
  //   web3forms  → endpoint: 'https://api.web3forms.com/submit', accessKey: '<key>'
  //   custom     → any endpoint that accepts JSON POST and returns 2xx
  form: { endpoint: '', accessKey: '', minSeconds: 3 },
};
