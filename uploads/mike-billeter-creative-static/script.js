
const mailSound = new Audio('assets/youve-got-mail-sound.mp3');
mailSound.preload = 'auto';

function playGotMail() {
  mailSound.currentTime = 0;
  void mailSound.play();
}

const form = document.querySelector('.retro-form');
if (form) {
  form.addEventListener('submit', (event) => {
    event.preventDefault();
    playGotMail();
    form.reset();
    form.querySelector('.form-success')?.remove();
    const status = document.createElement('output');
    status.className = 'form-success';
    status.setAttribute('aria-live', 'polite');
    status.innerHTML = '<strong>YOU\'VE GOT MAIL!</strong><span>Demo received. Connect your Mailgun endpoint to deliver it to Mike.</span>';
    form.append(status);
  });
}
