// Site-wide configuration. Edit here; nothing else needs to change.
export const SITE = {
  name: 'Mike Billeter Creative',
  // Public URL used for canonical links and structured data (no trailing slash).
  url: 'https://mikebilleter.com',
  email: 'mike.billeter@gmail.com',
  city: 'Sioux Falls',
  region: 'SD',
  subjectPrefix: '[Website Inquiry]',
  // Form delivery adapter. Leave endpoint empty and the form will say so honestly
  // and hand visitors a pre-filled mailto link instead. Supported out of the box:
  //   formspree  → endpoint: 'https://formspree.io/f/<id>'
  //   web3forms  → endpoint: 'https://api.web3forms.com/submit', accessKey: '<key>'
  //   custom     → any endpoint that accepts JSON POST and returns 2xx
  form: {
    endpoint: '',
    accessKey: '',
    // Minimum seconds between the form appearing and a submission (bot check).
    minSeconds: 3,
  },
};
