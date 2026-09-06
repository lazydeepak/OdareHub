(function () {
  function initLibraryExplorerWhenReady() {
    if (typeof window.gsInitLibraryExplorer === 'function') {
      window.gsInitLibraryExplorer(window.gsLibraryExplorerContext || {});
    }
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initLibraryExplorerWhenReady, { once: true });
  } else {
    initLibraryExplorerWhenReady();
  }
})();
