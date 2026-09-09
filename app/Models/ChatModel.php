<?php

namespace App\Models;

use \Config\Database;
use CodeIgniter\Model;
use App\Services\utility\FileService;

class ChatModel extends Model
{
    private FileService $fileService;

    public function __construct()
    {
        parent::__construct();
        $this->fileService = new FileService();
    }
    protected $table = 'chats';
    protected $primaryKey = 'id';
    protected $allowedFields = ['id', 'sender_id', 'receiver_id', 'message', 'file', 'file_type', 'receiver_type', 'sender_type', 'e_id'];

    public function chat_list($limit = 10, $offset = 0, $sort = 'id', $order = 'ASC', $e_id = null, $where = [], $orwhere = [], $search = '', $from_app = false, $receiver_id = null, $user_type = null, $currentUserId = null)
    {
        $db = Database::connect();
        $builder = $db->table('chats c');
        $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : $limit;
        $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : $offset;
        $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
        $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';

        $multipleWhere = [];
        if (!empty($search) || (isset($_GET['search']) && $_GET['search'] != '')) {
            $search = (isset($_GET['search']) && $_GET['search'] != '') ? $_GET['search'] : $search;
            $multipleWhere = [
                '`c.message`' => $search,
            ];
        }

        $builder->select('count(c.id) as total')
            ->join('users u', 'u.id=c.receiver_id');
        $this->applyChatWhere($builder, $where, $multipleWhere);
        if ($currentUserId !== null) {
            $this->excludeDeletedForUser($builder, (int) $currentUserId);
        }
        $total = $builder->get()->getRowArray()['total'];

        if ($user_type == "provider") {
            $builder->select('c.id,c.sender_id,c.receiver_id,c.sender_type,c.message,c.file,c.file_type,c.created_at,c.updated_at,u.username,u.image as profile_image,
            pd.company_name as receiver_name,r.image as receiver_profile_image,')
                ->join('users u', 'u.id=c.sender_id')
                ->join('users r', 'r.id = c.receiver_id')
                ->join('partner_details pd', '(pd.partner_id = c.receiver_id) OR (pd.partner_id = c.sender_id)');
        } else if ($user_type == "customer") {
            $builder->select('c.id,c.sender_id,c.receiver_id,c.booking_id,c.sender_type,c.message,c.file,c.file_type,c.created_at,c.updated_at,u.username,u.image as profile_image,r.username as receiver_name,r.image as receiver_profile_image,')
                ->join('users u', 'u.id=c.sender_id')
                ->join('users r', 'r.id = c.receiver_id');
        }
        $this->applyChatWhere($builder, $where, $multipleWhere);
        if ($currentUserId !== null) {
            $this->excludeDeletedForUser($builder, (int) $currentUserId);
        }
        $builder->orderBy($sort, $order);
        $chat_record = $builder->get()->getResultArray();

        $users = $this->fetchReceiverUsers($db, $user_type, $receiver_id);
        $receiverImage = $this->resolveReceiverImage($users);

        $bulkData = ['total' => $total];

        if (empty($chat_record)) {
            if (!empty($users)) {
                $users[0]['receiver_id'] = $users[0]['id'];
                $users[0]['receiver_name'] = $users[0]['username'];
                if ($receiverImage !== null) {
                    $users[0]['receiver_profile_image'] = $receiverImage;
                }
            }

            $bulkData['receiver_id'] = $receiver_id;
            $bulkData['receiver_name'] = !empty($users) ? $users[0]['username'] : '';
            if ($receiverImage !== null) {
                $bulkData['receiver_profile_image'] = $receiverImage;
            }
            $bulkData['rows'] = $users;

            return json_encode($bulkData);
        }

        $rows = [];
        foreach ($chat_record as $row) {
            $rows[] = $this->mapChatRow($row);
        }
        $bulkData['rows'] = $rows;
        $bulkData['receiver_id'] = $receiver_id;
        $bulkData['receiver_name'] = $users[0]['username'] ?? '';
        if ($receiverImage !== null) {
            $bulkData['receiver_profile_image'] = $receiverImage;
        }

        if ($from_app) {
            return ['total' => $total, 'data' => $rows];
        }

        return json_encode($bulkData);
    }

    /**
     * Returns the raw SQL string for the soft-delete exclusion filter.
     * Can be used inside complex correlated subqueries (like unread counts).
     */
    public function getExclusionSql(int $userId, string $alias = 'c'): string
    {
        return "NOT EXISTS (
                SELECT 1 FROM chat_deletions cd
                WHERE cd.user_id = {$userId}
                  AND (
                      cd.chat_id = {$alias}.id
                      OR (cd.chat_id IS NULL
                          AND cd.e_id = {$alias}.e_id
                          AND cd.booking_id <=> {$alias}.booking_id
                          AND cd.created_at >= {$alias}.created_at)
                  )
            )";
    }

    /**
     * Applies a NOT EXISTS clause to filter out chats that the given user has deleted.
     */
    public function excludeDeletedForUser($builder, int $userId, string $alias = 'c'): void
    {
        $builder->where($this->getExclusionSql($userId, $alias), null, false);
    }

    /**
     * Applies the shared filter chain (chat_list() runs this identically for
     * both the count query and the row query — extracted so the two stay in sync).
     */
    private function applyChatWhere($builder, array $where, array $multipleWhere): void
    {
        if (!empty($where)) {
            $builder->where($where);
        }
        if (!empty($multipleWhere)) {
            $builder->groupStart()->orLike($multipleWhere)->groupEnd();
        }
    }

    /**
     * Chat_list() needs the receiver's own user row in every code path
     * (empty-history stub, populated rows, image resolution) — fetched once
     * here instead of once per branch.
     */
    private function fetchReceiverUsers($db, ?string $user_type, $receiver_id): array
    {
        if ($user_type === 'customer') {
            return $db->table('users')->select('id,username,image')->where('id', $receiver_id)->get()->getResultArray();
        }
        if ($user_type === 'provider') {
            return $db->table('users u')->select('u.id,u.image,pd.company_name as username')
                ->where('u.id', $receiver_id)
                ->join('partner_details pd', 'pd.partner_id = u.id')
                ->get()->getResultArray();
        }

        return [];
    }

    /**
     * array_key_exists (not isset) — a genuinely NULL `image` column must still
     * resolve through FileService::url() to the default avatar, not be skipped.
     */
    private function resolveReceiverImage(array $users): ?string
    {
        if (empty($users) || !array_key_exists('image', $users[0])) {
            return null;
        }

        return $this->fileService->url($users[0]['image'], 'profile');
    }

    private function mapChatRow(array $row): array
    {
        $tempRow = [
            'id' => $row['id'],
            'sender_id' => $row['sender_id'],
            'receiver_id' => $row['receiver_id'],
            'sender_type' => $row['sender_type'] ?? null,
            'message' => $row['message'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'sender_name' => $row['username'],
            'receiver_name' => $row['receiver_name'],
            'booking_id' => $row['booking_id'] ?? null,
            'receiver_profile_image' => $this->fileService->url($row['receiver_profile_image'], 'profile'),
            'file_type' => $row['file_type'],
        ];

        if (array_key_exists('profile_image', $row)) {
            $tempRow['profile_image'] = $this->fileService->url($row['profile_image'], 'profile');
        }

        $tempRow['file'] = $this->resolveChatFiles($row['file']);

        return $tempRow;
    }

    private function resolveChatFiles($rawFile)
    {
        if (empty($rawFile)) {
            return is_array($rawFile) ? [] : '';
        }

        $files = [];
        foreach (json_decode($rawFile, true) as $data) {
            $files[] = [
                'file' => $this->fileService->url($data['file'], 'chat_attachment'),
                'file_type' => $data['file_type'],
                'file_name' => $data['file_name'],
                'file_size' => $data['file_size'],
            ];
        }

        return $files;
    }

    public function provider_booking_chat_list(
        $limit = 10,
        $offset = 0,
        $sort = 'id',
        $order = 'ASC',
        $e_id = null,
        $where = [],
        $orwhere = [],
        $search = '',
        $from_app = false,
        $receiver_id = null,
        $user_type = null,
        $booking_id = null,
        $provider_id = null,
        $currentUserId = null
    ) {
        $db = \Config\Database::connect();
        $builder = $db->table('chats c');
        $multipleWhere = [];
        $bulkData = $rows = $tempRow = [];
        $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : $limit;
        $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : $offset;
        $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
        $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
        if ((isset($search) && !empty($search) && $search != "") || (isset($_GET['search']) && $_GET['search'] != '')) {
            $search = (isset($_GET['search']) && $_GET['search'] != '') ? $_GET['search'] : $search;
            $multipleWhere = [
                '`c.message`' => $search,
            ];
        }
        $chat_count = $builder->select('count(c.id) as total')
            ->join('users u', 'u.id=c.receiver_id');
        if (isset($where) && !empty($where)) {
            $builder->where($where);
        }
        if (isset($multipleWhere) && !empty($multipleWhere)) {
            $builder->groupStart();
            $builder->orLike($multipleWhere);
            $builder->groupEnd();
        }
        if ($currentUserId !== null) {
            $this->excludeDeletedForUser($builder, (int) $currentUserId);
        }
        $chat_count = $builder->get()->getRowArray();
        $total = $chat_count['total'];
        $builder->select('c.id,c.sender_id,c.receiver_id,c.booking_id,c.sender_type,c.message,c.file,c.file_type,c.created_at,c.updated_at,u.username,u.image as profile_image,r.username as receiver_name,r.image as receiver_profile_image,')
            ->join('users u', 'u.id=c.sender_id')
            ->join('users r', 'r.id = c.receiver_id');
        $builder->where('c.booking_id', $booking_id)
            ->where('c.receiver_id', $provider_id);
        if (isset($where) && !empty($where)) {
            $builder->where($where);
        }
        if (isset($multipleWhere) && !empty($multipleWhere)) {
            $builder->groupStart();
            $builder->orLike($multipleWhere);
            $builder->groupEnd();
        }
        if ($currentUserId !== null) {
            $this->excludeDeletedForUser($builder, (int) $currentUserId);
        }
        $builder->orderBy($sort, $order);
        $chat_record = $builder->get()->getResultArray();
        $db = \Config\Database::connect();
        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $tempRow = array();

        if (empty($chat_record)) {
            if ($user_type == "customer") {
                $users = $db->table('users')->select('id,username,image')->where('id', $receiver_id)->get()->getResultArray();
            } elseif ($user_type == "provider") {
                $users = $db->table('users u')->select('u.id,u.image,pd.company_name as username')->where('u.id', $receiver_id)->join('partner_details pd', 'pd.partner_id = u.id')->get()->getResultArray();
            }
            $users[0]['receiver_id'] = $users[0]['id'];
            $users[0]['receiver_name'] = $users[0]['username'];
            $users[0]['receiver_profile_image'] = $this->fileService->url($users[0]['image'], 'profile');

            $bulkData['receiver_id'] = $receiver_id;
            $bulkData['receiver_name'] = $users[0]['username'];
            if ($user_type == "provider") {
                if (isset($users[0]['image'])) {
                    $bulkData['receiver_profile_image'] = $this->fileService->url($users[0]['image'], 'profile');
                }
            } else if ($user_type == "customer") {
                $bulkData['receiver_profile_image'] = $this->fileService->url($users[0]['image'], 'profile');
            }
            $bulkData['rows'] = $users;
            return json_encode($bulkData);
        } else {
            foreach ($chat_record as $row) {
                $tempRow['id'] = $row['id'];
                $tempRow['sender_id'] = $row['sender_id'];
                $tempRow['receiver_id'] = $row['receiver_id'];
                $tempRow['sender_type'] = $row['sender_type'] ?? null;
                $tempRow['message'] = $row['message'];
                $tempRow['created_at'] = $row['created_at'];
                $tempRow['updated_at'] = $row['updated_at'];
                $tempRow['sender_name'] = $row['username'];
                $tempRow['receiver_name'] = $row['receiver_name'];
                $tempRow['booking_id'] = $row['booking_id'] ?? null;
                $tempRow['receiver_profile_image'] = $this->fileService->url($row['receiver_profile_image'], 'profile');

                if (array_key_exists('profile_image', $row)) {
                    $tempRow['profile_image'] = $this->fileService->url($row['profile_image'], 'profile');
                }
                if (!empty($row['file'])) {
                    $decodedFiles = json_decode($row['file'], true);
                    $row['file'] = [];

                    foreach ($decodedFiles as $data) {
                        $row['file'][] = [
                            'file' => $this->fileService->url($data['file'], 'chat_attachment'),
                            'file_type' => $data['file_type'],
                            'file_name' => $data['file_name'],
                            'file_size' => $data['file_size'],
                        ];
                    }
                } else {
                    $row['file'] = is_array($row['file']) ? [] : "";
                }
                $tempRow['file_type'] = $row['file_type'];
                $tempRow['file'] = $row['file'];
                $rows[] = $tempRow;
            }
            $bulkData['rows'] = $rows;
            if ($user_type == "customer") {
                $users = $db->table('users')->select('id,username,image')->where('id', $receiver_id)->get()->getResultArray();
            } elseif ($user_type == "provider") {
                $users = $db->table('users u')->select('u.id,u.image,pd.company_name as username')->where('u.id', $receiver_id)->join('partner_details pd', 'pd.partner_id = u.id')->get()->getResultArray();
            }
            $bulkData['receiver_id'] = $receiver_id;
            $bulkData['receiver_name'] = $users[0]['username'];
            if ($user_type == "provider") {
                if (isset($users[0]['image'])) {
                    $bulkData['receiver_profile_image'] = $this->fileService->url($users[0]['image'], 'profile');
                }
            } else if ($user_type == "customer") {
                $bulkData['receiver_profile_image'] = $this->fileService->url($users[0]['image'], 'profile');
            }
            if ($from_app) {
                $data['total'] = $total;
                $data['data'] = $rows;
                return $data;
            } else {
                return json_encode($bulkData);
            }
        }
    }
}
