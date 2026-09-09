<?= $this->extend('backend/handyman/layouts/main') ?>

<?= $this->section('content') ?>

<div class="section-body">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">

                <!-- Table -->
                <section class="card-body p-0">
                    <div class="table-responsive">
                        <table
                            class="table table-hover mb-0"
                            id="reviews_table"
                            data-toggle="table"
                            data-side-pagination="server"
                            data-pagination="true"
                            data-url="<?= base_url('handyman/reviews/list_data') ?>"
                            data-sort-name="hr.id"
                            data-sort-order="desc"
                            data-page-list="[10, 25, 50, 100]"
                            data-pagination-successively-size="2"
                            data-show-refresh="true"
                            data-search="true"
                            data-show-columns="false"
                            data-show-export="false"
                            data-query-params="reviewQueryParams">
                            <thead class="thead-light">
                                <tr>
                                    <th data-field="customer_cell" data-sortable="false">
                                        <?= labels('customer', 'Customer') ?>
                                    </th>
                                    <th data-field="rating_stars" data-sortable="false" class="text-center" style="width: 140px;">
                                        <?= labels('rating', 'Rating') ?>
                                    </th>
                                    <th data-field="review_text" data-sortable="false">
                                        <?= labels('review', 'Review') ?>
                                    </th>
                                    <th data-field="images_cell" data-sortable="false" class="text-center" style="width: 160px;">
                                        <?= labels('images', 'Images') ?>
                                    </th>
                                    <th data-field="rated_on" data-sortable="false" class="text-center" style="width: 170px;">
                                        <?= labels('rated_on', 'Rated On') ?>
                                    </th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </section>

            </div>
        </div>
    </div>
</div>

<!-- Review Images Modal -->
<div id="reviewImagesModal" class="modal fade" tabindex="-1" aria-labelledby="reviewImagesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header d-flex justify-content-between align-items-center w-100">
                <h5 class="modal-title m-0" id="reviewImagesModalLabel"><?= labels('images', 'Images') ?></h5>
                <button type="button" class="close m-0" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body d-flex flex-wrap gap-2" id="reviewImagesModalBody">
                <!-- Images injected here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= labels('close', 'Close') ?></button>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('page_scripts') ?>
<script>
    window.reviewQueryParams = function (p) {
        return {
            search: p.search,
            limit:  p.limit,
            sort:   p.sort,
            order:  p.order,
            offset: p.offset,
        };
    };

    $(document).ready(function () {
        $(document).on('click', '.view-review-images', function () {
            var images = $(this).data('images');
            var $body = $('#reviewImagesModalBody').empty();

            if (Array.isArray(images) && images.length > 0) {
                images.forEach(function (url) {
                    $body.append(
                        '<a href="' + url + '" target="_blank" rel="noopener">' +
                        '<img src="' + url + '" class="rounded border" height="120" style="object-fit: cover;">' +
                        '</a>'
                    );
                });
            } else {
                $body.append('<p class="text-muted mb-0"><?= labels('no_images', 'No Images') ?></p>');
            }

            $('#reviewImagesModal').modal('show');
        });
    });
</script>
<?= $this->endSection() ?>
