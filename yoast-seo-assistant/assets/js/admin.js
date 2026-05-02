(function () {
  document.addEventListener('click', function (event) {
    var el = event.target;
    if (!el.matches('.kseo-confirm-bulk')) return;
    if (!window.confirm('Run AI SEO completion for all listed posts?')) {
      event.preventDefault();
    }
  });
})();
