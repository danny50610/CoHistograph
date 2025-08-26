<script src="https://cdn.datatables.net/v/bs5/dt-2.3.3/b-3.2.4/b-colvis-3.2.4/b-html5-3.2.4/datatables.min.js"
    integrity="sha384-6kHYzn90P5lWqH3zJ7j3/Xtwil3x4L3szsh0pztEmKmXl9rqkpAKRIgQ2TJ8BcBG" crossorigin="anonymous">
</script>

<script>
    // DataTables 預設設定
    (function($, DataTable) {
        $.extend(true, DataTable.defaults, {
            pageLength: 10,
            autoWidth: false,
            scrollX: true,
            stateSave: true,
            createdRow: function(row, data, dataIndex) {
                $(row).children('td').each(function() {
                    $(this).addClass('align-middle');
                })
            },
            language: {
                "decimal": "",
                "emptyTable": "沒有資料",
                "thousands": ",",
                "processing": "處理中...",
                "loadingRecords": "載入中...",
                "lengthMenu": "顯示_MENU_ 項結果",
                "zeroRecords": "沒有符合的結果",
                "info": "顯示第 _START_ 至 _END_ 項結果，共 _TOTAL_ 項",
                "infoEmpty": "顯示第 0 至 0 項結果，共 0 項",
                "infoFiltered": "(從 _MAX_ 項結果中過濾)",
                "infoPostFix": "",
                "search": "搜尋",
                "paginate": {
                    "first": "&laquo;",
                    "previous": "&lsaquo;",
                    "next": "&rsaquo;",
                    "last": "&raquo;"
                },
                "aria": {
                    "sortAscending": ": 升冪排列",
                    "sortDescending": ": 降冪排列"
                }
            }
        });
    })(jQuery, jQuery.fn.dataTable);
</script>
