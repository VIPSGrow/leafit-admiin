<?php

namespace App\Services\Service;

/**
 * Centralised validation rule arrays for service create/update.
 *
 * Removes the duplication between add_service() and update_service() where
 * nearly identical $validationRules arrays were repeated.
 */
class ServiceValidationRules
{
    /**
     * Rules for service creation.
     *
     * @param float $price           Current price (used in less_than rule for discounted_price)
     * @param bool  $cloning         When true, image is optional instead of required
     * @param bool  $skipPartnerRule When true, omits the 'partner' field requirement (partner panel context)
     */
    public static function forCreate(float $price, bool $cloning = false, bool $skipPartnerRule = false): array
    {
        $rules = [];

        if (!$skipPartnerRule) {
            $rules['partner'] = ['rules' => 'required', 'errors' => ['required' => labels('Please select provider', 'Please select provider')]];
        }

        $rules = array_merge($rules, [
            'categories'       => ['rules' => 'required', 'errors' => ['required' => labels(PLEASE_SELECT_CATEGORY, 'Please select category')]],
            'price'            => ['rules' => 'required|numeric', 'errors' => ['required' => labels(PLEASE_ENTER_PRICE, 'Please enter price'), 'numeric' => labels(PLEASE_ENTER_NUMERIC_VALUE_FOR_PRICE, 'Please enter numeric value for price')]],
            'discounted_price' => ['rules' => 'required|numeric|less_than[' . $price . ']', 'errors' => ['required' => labels(PLEASE_ENTER_DISCOUNTED_PRICE, 'Please enter discounted price'), 'numeric' => labels(PLEASE_ENTER_NUMERIC_VALUE_FOR_DISCOUNTED_PRICE, 'Please enter numeric value for discounted price'), 'less_than' => labels(DISCOUNTED_PRICE_SHOULD_BE_LESS_THAN_PRICE, 'Discounted price should be less than price')]],
            'members'          => ['rules' => 'required|numeric', 'errors' => ['required' => labels(PLEASE_ENTER_REQUIRED_MEMBER_FOR_SERVICE, 'Please enter required member for service'), 'numeric' => labels(PLEASE_ENTER_NUMERIC_VALUE_FOR_REQUIRED_MEMBER, 'Please enter numeric value for required member')]],
            'duration'         => ['rules' => 'required|numeric', 'errors' => ['required' => labels(PLEASE_ENTER_DURATION_TO_PERFORM_TASK, 'Please enter duration to perform task'), 'numeric' => labels(PLEASE_ENTER_NUMERIC_VALUE_FOR_DURATION_OF_TASK, 'Please enter numeric value for duration of task')]],
            'max_qty'          => ['rules' => 'required|numeric', 'errors' => ['required' => labels(PLEASE_ENTER_MAX_QUANTITY_ALLOWED_FOR_SERVICES, 'Please enter max quantity allowed for services'), 'numeric' => labels(PLEASE_ENTER_NUMERIC_VALUE_FOR_MAX_QUANTITY_ALLOWED_FOR_SERVICES, 'Please enter numeric value for max quantity allowed for services')]],
            'meta_title'       => ['rules' => 'permit_empty', 'errors' => ['permit_empty' => labels(META_TITLE_IS_OPTIONAL, 'Meta title is optional')]],
            'meta_description' => ['rules' => 'permit_empty', 'errors' => ['permit_empty' => labels(META_DESCRIPTION_IS_OPTIONAL, 'Meta description is optional')]],
            'meta_keywords'    => ['rules' => 'permit_empty', 'errors' => ['permit_empty' => labels(META_KEYWORDS_ARE_OPTIONAL, 'Meta keywords are optional')]],
            'meta_image'       => ['rules' => 'permit_empty|uploaded[meta_image]|is_image[meta_image]', 'errors' => ['permit_empty' => labels(META_IMAGE_IS_OPTIONAL, 'Meta image is optional'), 'uploaded' => labels(INVALID_META_IMAGE, 'Invalid meta image'), 'is_image' => labels(META_IMAGE_MUST_BE_A_VALID_IMAGE, 'Meta image must be a valid image')]],
            'schema_markup'    => ['rules' => 'permit_empty', 'errors' => ['permit_empty' => labels(SCHEMA_MARKUP_IS_OPTIONAL, 'Schema markup is optional')]],
            'service_slug'     => ['rules' => 'required', 'errors' => ['required' => labels(PLEASE_ENTER_SERVICE_SLUG, 'Please enter service slug')]],
        ]);

        $imageRule = $cloning
            ? 'permit_empty|uploaded[service_image_selector]|ext_in[service_image_selector,png,jpg,gif,jpeg,webp]|max_size[service_image_selector,8496]|is_image[service_image_selector]'
            : 'uploaded[service_image_selector]|ext_in[service_image_selector,png,jpg,gif,jpeg,webp]|max_size[service_image_selector,8496]|is_image[service_image_selector]';

        $rules['service_image_selector'] = [
            'rules'  => $imageRule,
            'errors' => [
                'uploaded' => labels(PLEASE_UPLOAD_AN_IMAGE_FILE, 'Please upload an image file'),
                'ext_in'   => labels(ONLY_JPEG_JPG_AND_PNG_FILES_ARE_ALLOWED, 'Only JPEG, JPG, and PNG files are allowed'),
                'is_image' => labels(FILE_MUST_BE_A_VALID_IMAGE, 'File must be a valid image'),
            ],
        ];

        return $rules;
    }

    /**
     * Rules for service update (image is always optional).
     *
     * @param bool $skipPartnerRule When true, omits the 'partner' field requirement (partner panel context)
     */
    public static function forUpdate(float $price, bool $skipPartnerRule = false): array
    {
        $rules = [];

        if (!$skipPartnerRule) {
            $rules['partner'] = ['rules' => 'required', 'errors' => ['required' => labels('please_select_provider', 'Please select provider')]];
        }

        $rules = array_merge($rules, [
            'categories'       => ['rules' => 'required', 'errors' => ['required' => labels('please_select_category', 'Please select category')]],
            'price'            => ['rules' => 'required|numeric', 'errors' => ['required' => labels('please_enter_price', 'Please enter price'), 'numeric' => labels('please_enter_numeric_value_for_price', 'Please enter numeric value for price')]],
            'discounted_price' => ['rules' => 'required|numeric|less_than[' . $price . ']', 'errors' => ['required' => labels('please_enter_discounted_price', 'Please enter discounted price'), 'numeric' => labels('please_enter_numeric_value_for_discounted_price', 'Please enter numeric value for discounted price'), 'less_than' => labels('discounted_price_should_be_less_than_price', 'Discounted price should be less than price')]],
            'members'          => ['rules' => 'required|numeric', 'errors' => ['required' => labels('please_enter_required_member_for_service', 'Please enter required member for service'), 'numeric' => labels('please_enter_numeric_value_for_required_member', 'Please enter numeric value for required member')]],
            'duration'         => ['rules' => 'required|numeric', 'errors' => ['required' => labels('please_enter_duration_to_perform_task', 'Please enter duration to perform task'), 'numeric' => labels('please_enter_numeric_value_for_duration_of_task', 'Please enter numeric value for duration of task')]],
            'max_qty'          => ['rules' => 'required|numeric', 'errors' => ['required' => labels('please_enter_max_quantity_allowed_for_services', 'Please enter max quantity allowed for services'), 'numeric' => labels('please_enter_numeric_value_for_max_quantity_allowed_for_services', 'Please enter numeric value for max quantity allowed for services')]],
        ]);

        if (isset($_FILES['service_image_selector']) && $_FILES['service_image_selector']['size'] > 0) {
            $rules['service_image_selector'] = [
                'rules' => 'uploaded[service_image_selector]|ext_in[service_image_selector,png,jpg,gif,jpeg,webp]|max_size[service_image_selector,8496]|is_image[service_image_selector]',
            ];
        }

        return $rules;
    }
}
