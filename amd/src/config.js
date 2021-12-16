define([], function () {
    window.requirejs.config({
        paths: {
           "html2pdf": M.cfg.wwwroot + '/report/assignfeedback_download/js/html2pdf/lib/html2pdf.bundle.min',
        },
        shim: {
            'html2pdf': {exports: 'html2pdf'},
        }
    });
});