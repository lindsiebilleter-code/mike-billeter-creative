// 1999 edition: exit controls, the "You've Got Mail" sound, and the contact form.
(function () {
  MBWFx.arrive();
  const page = document.body.dataset.page || 'index';
  const exit = () => MBWFx.go('/' + (page === 'index' ? '' : page) + location.hash);
  document.querySelectorAll('[data-exit]').forEach(b => b.addEventListener('click', exit));
  window.addEventListener('keydown', e => { if (e.key === 'Escape') exit(); });
  MBWFx.typed('y2k', exit);

  let audio;
  const play = () => { if (!audio) { audio = new Audio('../assets/y2k/youve-got-mail-sound.mp3'); audio.preload = 'auto'; } audio.currentTime = 0; audio.play().catch(() => {}); };

  const form = document.getElementById('contact-form');
  if (form && window.MBWContact) {
    form.addEventListener('submit', play);
    MBWContact.wire(form, { pendingLabel: 'SENDING…' });
  }
})();
