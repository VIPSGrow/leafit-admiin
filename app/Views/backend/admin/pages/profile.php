<div class="main-content profile-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= labels('my_profile', 'My Profile') ?></h1>
        </div>
        <div class="section-body">

            <div class="row justify-content-center">
                <div class="col-lg-6 col-md-10 col-sm-12">
                    <div class="card">
                        <form action="<?= base_url('admin/profile/update') ?>" method="post" accept-charset="utf-8" class="form-submit-event">
                            <div class="col mb-3" style="border-bottom: solid 1px #e5e6e9;">
                                <div class="toggleButttonPostition"><?= labels('edit_profile', 'Edit Profile') ?></div>

                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-12">

                                        <div class="form-group">
                                            <label for="username"><?= labels('username', "User Name") ?></label>
                                            <input type="text" class="form-control" name="username" id="username" value="<?= esc($data['username']) ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label for="email"><?= labels('email', 'Email') ?></label>
                                            <input type="email" class="form-control" name="email" id="email" value="<?= esc($data['email'] ?? '') ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="form-group ">
                                            <label for="phone"><?= labels('phone_number', 'Phone Number') ?></label>
                                            <input type="tel" id="phone" name="phone" class="form-control" value="<?= esc($data['phone']) ?>" required>
                                        </div>
                                    </div>

                                </div>
                                <div class="row align-items-center mt-4">
                                    <div class="col-md-9">
                                        <div class="form-group mb-0">
                                            <label for="file"><?= labels('change_profile_picture', "Change Profile Picture") ?></label>
                                            <input type="file" name="profile" class="filepond" id="file" accept="image/*">
                                        </div>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <?php if ($data['has_profile_image']) : ?>
                                            <a href="<?= esc($data['profile_image_url']) ?>" data-lightbox="image-1">
                                                <img height="80px" src="<?= esc($data['profile_image_url']) ?>" alt="" style="border-radius: 8px;">
                                            </a>
                                        <?php else : ?>
                                            <figure class="avatar avatar-xl mb-0" data-initial="<?= esc($data['profile_initial']) ?>"></figure>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="form-group d-flex justify-content-end mt-4">
                                    <button type="submit" class="btn bg-new-primary"><?= labels('save_changes', 'Save') ?></button>
                                </div>
                            </div>
                            <?= form_close() ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>