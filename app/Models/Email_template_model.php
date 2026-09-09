<?php
namespace App\Models;
use \Config\Database;
use CodeIgniter\Model;
use  app\Controllers\BaseController;
class Email_template_model  extends Model
{
    protected $table          = 'email_templates';
    protected $primaryKey     = 'id';
    protected $allowedFields  = ['type', 'subject', 'to', 'template', 'bcc', 'cc', 'parameters'];
    public function list($from_app = false, $search = '', $limit = 10, $offset = 0, $sort = 'id', $order = 'ASC', $where = [])
    {
        $db = \Config\Database::connect();
        $builder = $db->table('email_templates');
        $multipleWhere = [];
        $condition = $bulkData = $rows = $tempRow = [];
        $search = isset($_GET['search']) ? $_GET['search'] : $search;
        $limit = isset($_GET['limit']) ? $_GET['limit'] : $limit;
        $sort = ($_GET['sort'] ?? '') == 'id' ? 'id' : ($_GET['sort'] ?? $sort);
        $order = $_GET['order'] ?? $order;
        if (!empty($search)) {
            $multipleWhere = [
                'id' => $search,
                'type' => $search,
            ];
        }
        if (!empty($where)) {
            $builder->where($where);
        }
        if (!empty($multipleWhere)) {
            $builder->groupStart()->orLike($multipleWhere)->groupEnd();
        }
        $total = $builder->countAllResults(false);
        $template_record = $builder->select('*')
            ->orderBy($sort, $order)
            ->limit($limit, $offset)
            ->get()
            ->getResultArray();
        // Load translation model for language fallback
        $translatedEmailTemplateModel = new \App\Models\Translated_email_template_model();
        
        // Get current and default language codes
        $currentLanguage = get_current_language();
        $defaultLanguage = get_default_language();
        
        foreach ($template_record as $row) {
            $operations = '';
            $operations = '<div class="dropdown">
                    <a class="" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <button class="btn btn-secondary btn-sm px-3"> <i class="fas fa-ellipsis-v "></i></button>
                    </a>
                    <div class="dropdown-menu" aria-labelledby="dropdownMenuLink">';
            $operations .= '<a class="dropdown-item" href="' . base_url('/admin/settings/edit_email_template/' . $row['id']) . '"><i class="fa fa-pen mr-1 text-primary"></i>'. labels('edit_email_template', 'Edit Email Template') .'</a>';
            $operations .= '</div></div>';
            
            // Initialize with main table data as fallback
            $subject = $row['subject'];
            $template = $row['template'];
            
            // Try to get translation for current language
            $currentTranslation = $translatedEmailTemplateModel->getTranslatedTemplate($row['id'], $currentLanguage);
            
            if (!empty($currentTranslation)) {
                // Use current language translation
                $subject = !empty($currentTranslation['subject']) ? $currentTranslation['subject'] : $subject;
                $template = !empty($currentTranslation['template']) ? $currentTranslation['template'] : $template;
            } else if ($currentLanguage !== $defaultLanguage) {
                // Fallback to default language if current language translation not available
                $defaultTranslation = $translatedEmailTemplateModel->getTranslatedTemplate($row['id'], $defaultLanguage);
                
                if (!empty($defaultTranslation)) {
                    // Use default language translation
                    $subject = !empty($defaultTranslation['subject']) ? $defaultTranslation['subject'] : $subject;
                    $template = !empty($defaultTranslation['template']) ? $defaultTranslation['template'] : $template;
                }
            }
            // If no translation found at all, use main table data (already set above)
            
            $tempRow['id'] = $row['id'];
            $tempRow['type'] = labels($row['type'], $row['type']);
            $tempRow['subject'] = $subject;
            $tempRow['template'] = $template;
            $tempRow['bcc'] = $row['bcc'];
            $tempRow['cc'] = $row['cc'];
            $tempRow['operations'] = $operations;
            $rows[] = $tempRow;
        }
        $bulkData['total'] = $total;
        $bulkData['rows'] = $rows;
        return json_encode($bulkData);
    }
}
