<?php

namespace App\DataTables;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Html\Editor\Editor;
use Yajra\DataTables\Html\Editor\Fields;
use Yajra\DataTables\Services\DataTable;

class UsersDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder<User> $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', 'user.datatables.action', 0)
            ->editColumn('name', function (User $user) {
                return view('user.datatables.name', [
                    'name'  => $user->name,
                    'roles' => $user->roles()->orderby('id')->get(),
                ])->render();
            })
            ->rawColumns(['action', 'name']);
    }

    /**
     * Get the query source of dataTable.
     *
     * @return QueryBuilder<User>
     */
    public function query(User $model): QueryBuilder
    {
        return $model::with('roles');
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('users-table')
            ->columns($this->getColumns())
            ->addColumnBefore([
                'data'      => 'action',
                'title'     => '查閱',
                'width'     => '50px',
                'class'     => 'text-center',
                'orderable' => false,
            ])
            ->minifiedAjax()
            ->parameters($this->getBuilderParameters())
            ->parameters([
                'order'      => [[1, 'asc']],
                'pageLength' => 50,
                'dom'        => 'B' . config('datatables-buttons.parameters.dom'),
                'buttons'    => [
                    [
                        'extend'   => 'csv',
                        'text'     => '<i class="fas fa-file-csv"></i> 另存成 CSV',
                        'filename' => $this->filename(),
                    ],
                ],
            ]);
    }

    /**
     * Get the dataTable columns definition.
     */
    public function getColumns(): array
    {
        return [
            'id'    => ['title' => '#', 'class' => 'text-center'],
            'name'  => ['title' => '使用者'],
            'email' => ['title' => '信箱'],
        ];
    }

    /**
     * Get the filename for export.
     */
    protected function filename(): string
    {
        return 'Users_' . date('YmdHis');
    }
}
