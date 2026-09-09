<?php

namespace App\Controllers\Admin;

use App\Models\Country_code_model;
use Exception;

class CountryCodeController extends Admin
{
    protected $superadmin;
    protected $validation;
    private Country_code_model $countryCodeModel;

    public function __construct()
    {
        parent::__construct();
        $this->validation      = \Config\Services::validation();
        $this->superadmin      = $this->session->get('email');
        $this->countryCodeModel = new Country_code_model();
        helper('ResponceServices');
    }

    public function contry_codes()
    {
        try {
            $this->data['available_countries'] = $this->getAvailableCountriesForImport();
            setPageInfo($this->data, labels('Country Code Settings', 'Country Code Settings') . '  | ' . labels('admin_panel', 'Admin Panel'), 'country_code');
            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/Admin/CountryCodeController.php - contry_codes()');
            $this->data['available_countries'] = [];
            setPageInfo($this->data, labels('Country Code Settings', 'Country Code Settings') . '  | ' . labels('admin_panel', 'Admin Panel'), 'country_code');
            return view('backend/admin/template', $this->data);
        }
    }

    public function import_country_codes()
    {
        try {
            $result = checkModificationInDemoMode($this->superadmin);
            if (isset($result['error']) && $result['error']) {
                return $this->response->setJSON($result);
            }

            $countriesData = $this->request->getPost('countries_data');

            if (empty($countriesData)) {
                return JsonError(labels('select_at_least_one_country_to_import', 'Please select at least one country to import'));
            }

            $existingCountries    = $this->countryCodeModel->select('country_code, calling_code')->findAll();
            $existingCountryCodes = array_column($existingCountries, 'country_code');

            $validation      = \Config\Services::validation();
            $importedCount   = 0;
            $skippedCount    = 0;
            $errors          = [];

            $validationRules = [
                'country_name' => 'required|max_length[100]|trim',
                'country_code' => 'required|exact_length[2]|alpha|trim',
                'calling_code' => 'required|regex_match[/^\+\d{1,7}$/]|trim',
                'flag_image'   => 'required|trim',
            ];

            $validationMessages = [
                'country_name' => ['required' => 'Country name is required', 'max_length' => 'Country name must be less than 100 characters'],
                'country_code' => ['required' => 'Country code is required', 'exact_length' => 'Country code must be exactly 2 characters', 'alpha' => 'Country code must contain only letters'],
                'calling_code' => ['required' => 'Calling code is required', 'regex_match' => 'Calling code must start with + followed by 1-7 digits'],
                'flag_image'   => ['required' => 'Flag image is required'],
            ];

            $db = \Config\Database::connect();
            $db->transStart();

            foreach ($countriesData as $index => $countryData) {
                $validation->setRules($validationRules, $validationMessages);

                if (!$validation->run($countryData)) {
                    $errors[] = "Row " . ($index + 1) . ": " . implode(', ', $validation->getErrors());
                    continue;
                }

                $countryName = trim($countryData['country_name']);
                $countryCode = strtoupper(trim($countryData['country_code']));
                $callingCode = trim($countryData['calling_code']);
                $flagImage   = trim($countryData['flag_image']);

                $flagFileName = basename(parse_url($flagImage, PHP_URL_PATH));
                if (!preg_match('/\.(png|jpg|jpeg|gif|svg)$/i', $flagFileName)) {
                    $errors[] = "Row " . ($index + 1) . ": " . labels('invalid_flag_image_format', 'Invalid flag image format');
                    continue;
                }

                if (in_array($countryCode, $existingCountryCodes)) {
                    $skippedCount++;
                    continue;
                }

                $insertData = [
                    'country_name' => $countryName,
                    'country_code' => $countryCode,
                    'calling_code' => $callingCode,
                    'flag_image'   => $flagFileName,
                    'is_default'   => 0,
                ];

                if ($this->countryCodeModel->save($insertData)) {
                    $importedCount++;
                    $existingCountryCodes[] = $countryCode;
                } else {
                    $errors[] = labels('failed_to_save', 'Failed to save') . ": " . $countryName;
                }
            }

            $db->transComplete();

            if ($db->transStatus() === false) {
                throw new Exception(labels('database_transaction_failed', 'Database transaction failed'));
            }

            $message = !empty($errors)
                ? implode(', ', $errors)
                : labels('country_codes_imported_successfully', 'Country code(s) imported successfully');

            return JsonSuccess($message);
        } catch (\Throwable $th) {
            \Config\Database::connect()->transRollback();
            throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/Admin/CountryCodeController.php - import_country_codes()');
            return JsonError(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"));
        }
    }

    public function add_contry_code()
    {
        try {
            $result = checkModificationInDemoMode($this->superadmin);
            if (isset($result['error']) && $result['error']) {
                return JsonError($result['message']);
            }

            $this->validation->setRules([
                'name' => ['rules' => 'required', 'errors' => ['required' => 'Please enter name']],
                'code' => ['rules' => 'required', 'errors' => ['required' => 'Please enter code']],
            ]);
            if (!$this->validation->withRequest($this->request)->run()) {
                $errors             = $this->validation->getErrors();
                return JsonError(labels($errors, $errors));
            }
            $data['code'] = $_POST['code'];
            $data['name'] = $_POST['name'];
            if ($this->countryCodeModel->save($data)) {
                return JsonSuccess(labels('data_saved_successfully', 'Data saved successfully'));
            } else {
                return JsonError(labels('error_occured', 'An error occured'));
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/Admin/CountryCodeController.php - add_contry_code()');
            return JsonError(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"));
        }
    }

    public function fetch_contry_code()
    {
        $limit  = (isset($_GET['limit'])  && !empty($_GET['limit']))  ? $_GET['limit']  : 10;
        $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
        $sort   = (isset($_GET['sort'])   && !empty($_GET['sort']))   ? $_GET['sort']   : 'id';
        $order  = (isset($_GET['order'])  && !empty($_GET['order']))  ? $_GET['order']  : 'ASC';
        $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
        return $this->countryCodeModel->list(false, $search, $limit, $offset, $sort, $order, []);
    }

    public function delete_contry_code()
    {
        try {
            $result = checkModificationInDemoMode($this->superadmin);
            if (isset($result['error']) && $result['error']) {
                return JsonError($result['message']);
            }

            $id      = $this->request->getVar('id');
            $default = $this->countryCodeModel->where('is_default', 1)->first();

            if (!empty($default) && $default['id'] == $id) {
                return JsonError(labels('default_country_code_cannot_be_removed', 'Default country code cannot be removed'));
            }

            if ($this->countryCodeModel->delete($id)) {
                return JsonSuccess(labels('data_deleted_successfully', 'Data deleted successfully'));
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/Admin/CountryCodeController.php - delete_contry_code()');
            return JsonError(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"));
        }
    }

    public function store_default_country_code()
    {
        try {
            $result = checkModificationInDemoMode($this->superadmin);
            if (isset($result['error']) && $result['error']) {
                return JsonError($result['message']);
            }

            $currentDefault = $this->countryCodeModel->where('is_default', 1)->first();
            if (!empty($currentDefault)) {
                $this->countryCodeModel->update($currentDefault['id'], ['is_default' => 0]);
            }
            $this->countryCodeModel->update($_POST['id'], ['is_default' => 1]);

            return JsonSuccess(labels('default_setted', 'Default setted'));
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/Admin/CountryCodeController.php - store_default_country_code()');
            return JsonError(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"));
        }
    }

    public function update_country_codes()
    {
        try {
            $result = checkModificationInDemoMode($this->superadmin);
            if ($result !== true) {
                return $this->response->setJSON($result);
            }
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            $this->validation->setRules([
                'name' => ['rules' => 'required', 'errors' => ['required' => 'Please enter name']],
                'code' => ['rules' => 'required', 'errors' => ['required' => 'Please enter code']],
            ]);
            if (!$this->validation->withRequest($this->request)->run()) {
                $errors             = $this->validation->getErrors();
                $response['error']    = true;
                $response['message']  = $errors;
                $response['csrfName'] = csrf_token();
                $response['csrfHash'] = csrf_hash();
                $response['data']     = [];
                return $this->response->setJSON($response);
            }
            $data['code'] = $_POST['code'];
            $data['name'] = $_POST['name'];
            if ($this->countryCodeModel->update($_POST['id'], $data)) {
                return json_encode([
                    'error'    => false,
                    'message'  => labels('Country code updated successfully', 'Country code updated successfully'),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                    'data'     => [],
                ]);
            } else {
                return json_encode([
                    'error'    => true,
                    'message'  => labels('Please try again....', 'Please try again....'),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                    'data'     => [],
                ]);
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/Admin/CountryCodeController.php - update_country_codes()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function get_available_countries()
    {
        try {
            return JsonSuccess(message: labels('countries_fetched_successfully', 'Countries fetched successfully'), extra: ['available_countries' => $this->getAvailableCountriesForImport()]);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/Admin/CountryCodeController.php - get_available_countries()');
            return JsonError(labels('something_went_wrong', 'Something went wrong'));
        }
    }

    private function getAvailableCountriesForImport(): array
    {
        $jsonPath = FCPATH . 'public/country_codes.json';

        if (!file_exists($jsonPath)) {
            return [];
        }

        $countryCodes = json_decode(file_get_contents($jsonPath), true);
        if (!$countryCodes) {
            return [];
        }

        $existingCodes      = $this->countryCodeModel->select('calling_code, country_name, country_code')->findAll();
        $existingCodesArray = array_column($existingCodes, 'country_code');

        $availableCountries = [];
        foreach ($countryCodes as $countryName => $details) {
            $countryCode = $details['country_code'] ?? '';
            if (!in_array($countryCode, $existingCodesArray)) {
                $availableCountries[] = [
                    'country_name' => $countryName,
                    'calling_code' => $details['calling_code'],
                    'country_code' => $countryCode,
                    'flag_image'   => $details['flag_image']
                        ? base_url('public/backend/assets/country_flags/' . $details['flag_image'])
                        : '',
                ];
            }
        }

        usort($availableCountries, fn($a, $b) => strcmp($a['country_name'], $b['country_name']));

        return $availableCountries;
    }
}
