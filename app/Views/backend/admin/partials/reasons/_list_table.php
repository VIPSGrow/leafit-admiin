<div class="container-fluid card">
    <div class="row ">
        <div class="col mb-12" style="border-bottom: solid 1px #e5e6e9;">
            <div class="toggleButttonPostition"><?= $list_title ?></div>
        </div>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-12">
                <div class="row mt-4 mb-3 ">
                    <div class="col-md-4 col-sm-2 mb-2">
                        <div class="input-group">
                            <input type="text" class="form-control" id="customSearch" placeholder="<?= labels('search_here', 'Search here!') ?>" aria-label="Search" aria-describedby="customSearchBtn">
                            <div class="input-group-append">
                                <button class="btn btn-primary" id="customSearchBtn" type="button">
                                    <i class="fa fa-search d-inline"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="dropdown d-inline ml-2">
                        <button class="btn export_download dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown"
                            aria-haspopup="true" aria-expanded="false">
                            <?= labels('download', 'Download') ?>
                        </button>
                        <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                            <a class="dropdown-item" onclick="custome_export('pdf','<?= $type ?>_reasons_list','user_list');"><?= labels('pdf', 'PDF') ?></a>
                            <a class="dropdown-item" onclick="custome_export('excel','<?= $type ?>_reasons_list','user_list');"><?= labels('excel', 'Excel') ?></a>
                            <a class="dropdown-item" onclick="custome_export('csv','<?= $type ?>_reasons_list','user_list')"><?= labels('csv', 'CSV') ?></a>
                        </div>
                    </div>
                </div>
                <table class="table " data-fixed-columns="true" id="user_list" data-detail-formatter="user_formater"
                    data-auto-refresh="true" data-toggle="table"
                    data-url="<?= $list_url ?>" data-side-pagination="server" data-pagination="true" data-page-list="[5, 10, 25, 50, 100, 200, All]"
                    data-search="false" data-show-columns="false" data-show-columns-search="true" data-show-refresh="false" data-sort-name="id" data-sort-order="DESC"
                    data-query-params="<?= $type ?>_reasons_query_params" data-pagination-successively-size="2">
                    <thead>
                        <tr>
                            <th data-field="id" class="text-center" data-sortable="true"><?= labels('id', 'ID') ?></th>
                            <th data-field="reason" class="text-center" data-formatter="reasonFormatter"><?= labels('reason', 'Reason') ?></th>
                            <th data-field="needs_additional_info_badge" class="text-center"><?= labels('needs_additional_info', 'Needs Additional Info') ?></th>
                            <th data-field="created_at" class="text-center" data-visible="false"><?= labels('created_at', 'Created At') ?></th>
                            <th data-field="operations" class="text-center" data-events="<?= $type ?>_reasons_events"><?= labels('operations', 'Operations') ?></th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>
