import 'datatables.net-bs5';
import 'datatables.net-fixedcolumns-bs5';
import 'datatables.net-fixedheader-bs5';
import 'datatables.net-select-bs5';
import 'datatables.net-buttons';
import 'datatables.net-buttons-bs5';
import 'datatables.net-responsive';
import 'datatables.net-responsive-bs5';
import 'datatables.net-rowgroup-bs5';
import Checkbox from 'jquery-datatables-checkboxes';

// Deliberately NOT bundled: JSZip, pdfMake (+ its ~1MB font file), buttons.html5 and buttons.print.
// They only power the client-side "excel / pdf / csv / copy / print" buttons, and every real page of
// this app exports on the server (window.gcmExport -> /api/v1/.../export, with the list's filters and
// proper Arabic PDFs) — so they added ~1.7MB to every single table page for nothing. The leftover
// Vuexy demo pages that still declare those buttons are not part of the product.
$.fn.dataTable.ext.Checkbox = Checkbox(window, $);
