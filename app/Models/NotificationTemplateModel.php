<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Notification Template Model
 * 
 * Handles database operations for notification templates
 * Stores notification templates for different events
 */
class NotificationTemplateModel extends Model
{
    protected $table = 'notification_templates';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        // Base table is read-only for translatable fields; only non-translatable fields remain here
        'event_key',
        'parameters'
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    /**
     * Get notification templates with operations for editing (with pagination support)
     * 
     * Returns formatted data with operations column containing edit buttons
     * Each template row includes HTML for edit operations dropdown
     * Returns data in Bootstrap Table format with total count for server-side pagination
     * Supports limit, offset, sort, and order parameters for Bootstrap Table pagination
     * 
     * @param int $limit Number of records to return (default: 10)
     * @param int $offset Number of records to skip (default: 0)
     * @param string $sort Column name to sort by (default: 'id')
     * @param string $order Sort order: 'asc' or 'desc' (default: 'desc')
     * @return array Array with 'total' count and 'rows' containing notification templates with operations column
     */
    public function getNotificationTemplates(int $limit = 10, int $offset = 0, string $sort = 'id', string $order = 'desc')
    {
        // Get total count of all templates first (before applying limit/offset)
        // This is needed for Bootstrap Table server-side pagination to show correct total pages
        $total = $this->countAllResults();

        // Validate and sanitize parameters
        // Ensure limit is at least 1 and offset is non-negative
        $limit = max(1, $limit);
        $offset = max(0, $offset);

        // Validate sort field to prevent SQL injection
        // Only allow sorting by these safe column names
        $allowedSortFields = ['id', 'event_key', 'created_at', 'updated_at'];
        if (!in_array($sort, $allowedSortFields)) {
            $sort = 'id'; // Default to id if invalid sort field provided
        }

        // Validate order to be either 'asc' or 'desc' (case-insensitive)
        $order = strtolower($order) === 'asc' ? 'asc' : 'desc';

        // Build query with sorting, limit, and offset
        // This ensures we only fetch the records needed for the current page
        // Use database connection directly for consistency with other models in codebase
        $db = \Config\Database::connect();
        $builder = $db->table($this->table);
        $builder->orderBy($sort, $order);
        $builder->limit($limit, $offset);

        // Execute query to get paginated templates
        $templates = $builder->get()->getResultArray();

        // Format each template with operations column
        // Only format the templates we fetched (not all templates)
        $formattedTemplates = [];

        foreach ($templates as $template) {
            // Build operations dropdown HTML for edit button
            // This follows the same pattern as email templates for consistency
            $operations = '<div class="dropdown">
                    <a class="" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <button class="btn btn-secondary btn-sm px-3"> <i class="fas fa-ellipsis-v "></i></button>
                    </a>
                    <div class="dropdown-menu" aria-labelledby="dropdownMenuLink">';

            // Add edit button in operations dropdown
            // Edit link will point to edit route (to be created in controller)
            $operations .= '<a class="dropdown-item" href="' . base_url('/admin/settings/edit-notification-template/') . $template['id'] . '"><i class="fa fa-pen mr-1 text-primary"></i>' . labels('edit_template', 'Edit Template') . '</a>';
            $operations .= '</div></div>';

            // Add operations column to template data
            $template['operations'] = $operations;

            // Add formatted template to result array
            $formattedTemplates[] = $template;
        }

        // Return in Bootstrap Table format with total count
        // Bootstrap Table expects 'total' for pagination and 'rows' for data
        // Total is the count of ALL records (not just current page) for proper pagination
        return [
            'total' => $total,
            'rows' => $formattedTemplates
        ];
    }

    /**
     * Get notification template by ID
     * 
     * Fetches a single notification template from the database using the provided ID
     * Returns the template data as an array, or null if template not found
     * 
     * @param int $id Template ID to fetch
     * @return array|null Template data or null if not found
     */
    public function getNotificationTemplate(int $id): ?array
    {
        // Use find() method to get template by primary key (id)
        // This is the standard CodeIgniter way to fetch by primary key
        $template = $this->find($id);

        // Return template data or null if not found
        return $template ?: null;
    }

    /**
     * Get notification template with translations organized by language code
     */
    public function getNotificationTemplateWithTranslations(int $id): ?array
    {
        $template = $this->getNotificationTemplate($id);
        if (!$template) {
            return null;
        }

        $translationModel = new \App\Models\TranslatedNotificationTemplateModel();
        $translations = [];
        foreach ($translationModel->getTemplateTranslations($id) as $row) {
            $translations[$row['language_code']] = $row;
        }

        $template['translations'] = $translations;
        return $template;
    }
}
