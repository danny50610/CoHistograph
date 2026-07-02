<link rel="stylesheet" href="https://cdn.datatables.net/v/bs5/dt-2.3.3/b-3.2.4/b-colvis-3.2.4/b-html5-3.2.4/datatables.min.css"
      integrity="sha384-WXwCQTp1RMMX1QuS/ZYMUJW7S9tC2C+JanLHyeNo3UAv6vFcmgGi2Y+I4ly7+wG8" crossorigin="anonymous">
<style>
{{-- 讓顯示數量的左邊有空隙--}}
div.dataTables_wrapper div.dataTables_length select {
    margin-left: .5rem;
}

{{-- 讓 處理中... 框框在中間 --}}
.dataTables_wrapper {
    position: relative;
}

.dataTables_processing {
    z-index: 99;
}

.ws-nowrap {
    white-space: nowrap;
}

.wb-break-word {
    word-break: break-word;
}

{{-- 讓 pagination 超過螢幕寬度時，可以捲動 --}}
div.dataTables_wrapper div.dataTables_paginate ul.pagination {
    justify-content: flex-start;
    overflow-x: auto;
}

@media (min-width: 768px) {
    .datatable-min-width {
        min-width: 30vw;
    }
}

@media (max-width: 767.98px) {
    .datatable-min-width {
        min-width: 60vw;
    }
}
</style>
