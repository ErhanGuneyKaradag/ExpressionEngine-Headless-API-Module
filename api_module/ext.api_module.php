<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * API Module Extension
 * 
 * @package     ExpressionEngine
 * @category    Extension
 * @author      Your Name
 * @link        https://yoursite.com
 */

class Api_module_ext
{
    public $settings        = array();
    public $name            = 'API Module Extension';
    public $version         = '1.0.0';
    public $description     = 'Handles API routing and authentication';
    public $settings_exist  = 'n';
    public $docs_url        = '';

    public function __construct($settings = array())
    {
        $this->settings = $settings;
    }

    /**
     * Parse file grid field
     */
    private function parse_file_grid_field($field_id, $entry_id)
    {
        $files = array();
        
        // Grid tablo adı
        $table_name = 'channel_grid_field_' . $field_id;
        
        // Önce tablonun var olup olmadığını kontrol et
        if (!ee()->db->table_exists($table_name)) {
            return null;
        }
        
        try {
            // Grid verilerini direkt çek
            $grid_data = ee()->db->select('*')
                ->where('entry_id', $entry_id)
                ->order_by('row_order', 'asc')
                ->get($table_name)
                ->result_array();
            
            if (empty($grid_data)) {
                return null;
            }
            
            foreach ($grid_data as $row) {
                $file_data = array();
                
                // Row bilgilerini ekle
                if (isset($row['row_id'])) {
                    $file_data['row_id'] = $row['row_id'];
                }
                if (isset($row['row_order'])) {
                    $file_data['row_order'] = $row['row_order'];
                }
                
                // Her kolonun değerini al
                foreach ($row as $col_key => $col_value) {
                    // col_id_X formatındaki kolonları işle
                    if (preg_match('/^col_id_(\d+)$/', $col_key, $matches)) {
                        $col_id = $matches[1];
                        
                        if (empty($col_value)) {
                            continue;
                        }
                        
                        // File field kontrolü - {filedir_X}filename formatı
                        if (preg_match('/\{filedir_(\d+)\}(.+)/', $col_value, $file_matches)) {
                            $upload_id = $file_matches[1];
                            $filename = $file_matches[2];
                            
                            $upload_pref = ee()->db->select('url, server_path')
                                ->where('id', $upload_id)
                                ->get('upload_prefs')
                                ->row();
                            
                            if ($upload_pref) {
                                $url = $this->parse_ee_variables($upload_pref->url);
                                
                                $file_data['image'] = array(
                                    'url' => rtrim($url, '/') . '/' . $filename,
                                    'filename' => $filename,
                                    'upload_id' => $upload_id
                                );
                            }
                        } 
                        // File ID ise
                        elseif (is_numeric($col_value)) {
                            $file = ee()->db->select('file_name, file_id, upload_location_id, title, mime_type, file_size')
                                ->where('file_id', $col_value)
                                ->get('files')
                                ->row();
                            
                            if ($file) {
                                $upload_pref = ee()->db->select('url')
                                    ->where('id', $file->upload_location_id)
                                    ->get('upload_prefs')
                                    ->row();
                                
                                if ($upload_pref) {
                                    $url = $this->parse_ee_variables($upload_pref->url);
                                    
                                    $file_data['image'] = array(
                                        'file_id' => $file->file_id,
                                        'url' => rtrim($url, '/') . '/' . $file->file_name,
                                        'filename' => $file->file_name,
                                        'title' => $file->title,
                                        'mime_type' => $file->mime_type,
                                        'file_size' => $file->file_size
                                    );
                                }
                            }
                        } else {
                            // Text field gibi diğer kolonlar (caption, description vs)
                            $file_data['col_' . $col_id] = $col_value;
                        }
                    }
                }
                
                if (!empty($file_data)) {
                    $files[] = $file_data;
                }
            }
            
        } catch (Exception $e) {
            log_message('error', 'File grid parse error for field_id ' . $field_id . ': ' . $e->getMessage());
            return null;
        }
        
        return !empty($files) ? $files : null;
    }

    /**
     * Parse grid field
     */
    private function parse_grid_field($field_id, $entry_id)
    {
        // Grid tablo adı
        $table_name = 'grid_field_' . $field_id;
        
        // Tablo var mı kontrol et
        if (!ee()->db->table_exists($table_name)) {
            return null;
        }
        
        // Grid verilerini çek
        $grid_data = ee()->db->select('*')
            ->where('entry_id', $entry_id)
            ->where('field_id', $field_id)
            ->order_by('row_order', 'asc')
            ->get($table_name)
            ->result_array();
        
        if (empty($grid_data)) {
            return array();
        }
        
        $rows = array();
        foreach ($grid_data as $row) {
            $row_data = array();
            
            foreach ($row as $col_key => $col_value) {
                // col_id_X formatındaki kolonları al
                if (preg_match('/^col_id_(\d+)$/', $col_key)) {
                    $row_data[$col_key] = $col_value;
                }
            }
            
            if (!empty($row_data)) {
                $rows[] = $row_data;
            }
        }
        
        return $rows;
    }

    /**
     * Parse file field value
     */
    private function parse_file_field($value)
    {
        // {filedir_X}filename.jpg formatını parse et
        if (preg_match('/\{filedir_(\d+)\}(.+)/', $value, $file_matches)) {
            $upload_id = $file_matches[1];
            $filename = $file_matches[2];
            
            // Upload directory bilgisini al
            $upload_pref = ee()->db->select('url, server_path')
                ->where('id', $upload_id)
                ->get('upload_prefs')
                ->row();
            
            if ($upload_pref) {
                $url = $this->parse_ee_variables($upload_pref->url);
                $path = $this->parse_ee_variables($upload_pref->server_path);
                
                return array(
                    'url' => rtrim($url, '/') . '/' . $filename,
                    'path' => rtrim($path, '/') . '/' . $filename,
                    'filename' => $filename,
                    'original' => $value
                );
            }
        }
        // Sadece file_id varsa
        elseif (is_numeric($value)) {
            $file = ee()->db->select('file_name, file_id, upload_location_id, title, description, mime_type, file_size')
                ->where('file_id', $value)
                ->get('files')
                ->row();
            
            if ($file) {
                $upload_pref = ee()->db->select('url, server_path')
                    ->where('id', $file->upload_location_id)
                    ->get('upload_prefs')
                    ->row();
                
                if ($upload_pref) {
                    $url = $this->parse_ee_variables($upload_pref->url);
                    $path = $this->parse_ee_variables($upload_pref->server_path);
                    
                    return array(
                        'file_id' => $file->file_id,
                        'url' => rtrim($url, '/') . '/' . $file->file_name,
                        'path' => rtrim($path, '/') . '/' . $file->file_name,
                        'filename' => $file->file_name,
                        'title' => $file->title,
                        'description' => $file->description,
                        'mime_type' => $file->mime_type,
                        'file_size' => $file->file_size
                    );
                }
            }
        }
        
        return $value;
    }

    /**
     * Parse EE template variables like {base_url}, {base_path}
     */
    private function parse_ee_variables($string)
    {
        $base_url = ee()->config->item('base_url');
        $base_path = ee()->config->item('base_path');
        
        $string = str_replace('{base_url}', rtrim($base_url, '/'), $string);
        $string = str_replace('{base_path}', rtrim($base_path, '/'), $string);
        
        return $string;
    }

    /**
     * Get entry categories
     */
    private function get_entry_categories($entry_id)
    {
        $categories = array();
        
        // Entry'nin kategori ilişkilerini çek
        $query = ee()->db->select('c.cat_id, c.cat_name, c.cat_url_title, c.cat_description, c.cat_image, cg.group_name, cg.group_id')
            ->from('category_posts cp')
            ->join('categories c', 'cp.cat_id = c.cat_id', 'left')
            ->join('category_groups cg', 'c.group_id = cg.group_id', 'left')
            ->where('cp.entry_id', $entry_id)
            ->order_by('c.cat_order', 'asc')
            ->get();
        
        foreach ($query->result() as $cat) {
            $cat_data = array(
                'cat_id' => $cat->cat_id,
                'cat_name' => $cat->cat_name,
                'cat_url_title' => $cat->cat_url_title,
                'group_id' => $cat->group_id,
                'group_name' => $cat->group_name
            );
            
            // Eğer açıklama varsa ekle
            if (!empty($cat->cat_description)) {
                $cat_data['cat_description'] = $cat->cat_description;
            }
            
            // Eğer görsel varsa parse et
            if (!empty($cat->cat_image)) {
                $cat_data['cat_image'] = $this->parse_file_field($cat->cat_image);
            }
            
            $categories[] = $cat_data;
        }
        
        return !empty($categories) ? $categories : null;
    }

    /**
     * Activate Extension
     */
    public function activate_extension()
    {
        $data = array(
            'class'    => __CLASS__,
            'method'   => 'route_api',
            'hook'     => 'core_boot',
            'settings' => serialize($this->settings),
            'priority' => 1,
            'version'  => $this->version,
            'enabled'  => 'y'
        );

        ee()->db->insert('extensions', $data);
    }

    /**
     * Update Extension
     */
    public function update_extension($current = '')
    {
        if ($current == '' OR $current == $this->version) {
            return FALSE;
        }
        return TRUE;
    }

    /**
     * Disable Extension
     */
    public function disable_extension()
    {
        ee()->db->where('class', __CLASS__);
        ee()->db->delete('extensions');
    }

    /**
     * Route API requests
     */
    public function route_api()
    {
        $uri = ee()->uri->uri_string();
        
        // /api ile başlayan istekleri yakala
        if (strpos($uri, 'api/') === 0) {
            $segments = explode('/', trim($uri, '/'));
            
            if (count($segments) < 2) {
                $this->send_json_response(['error' => 'Invalid API endpoint'], 400);
            }

            $endpoint = $segments[1];

            switch ($endpoint) {
                case 'token':
                    $this->handle_token_request();
                    break;
                case 'entries':
                    $this->handle_entries_request();
                    break;
                case 'categories':
                    $this->handle_categories_request();
                    break;
                default:
                    $this->send_json_response(['error' => 'Endpoint not found'], 404);
            }
        }
    }

    /**
     * Handle token generation request
     */
    private function handle_token_request()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->send_json_response(['error' => 'Method not allowed'], 405);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        
        $username = isset($input['username']) ? $input['username'] : null;
        $password = isset($input['password']) ? $input['password'] : null;

        if (!$username || !$password) {
            $this->send_json_response(['error' => 'Username and password required'], 400);
        }

        $member = ee()->db->select('member_id, username, password, salt')
            ->where('username', $username)
            ->get('members')
            ->row();

        if (!$member) {
            $this->send_json_response(['error' => 'Invalid credentials'], 401);
        }

        $valid = $this->verify_password($password, $member->password, $member->salt);

        if (!$valid) {
            $this->send_json_response(['error' => 'Invalid credentials'], 401);
        }

        $token = $this->generate_token($member->member_id);

        $this->send_json_response([
            'success' => true,
            'token' => $token,
            'expires_in' => 86400
        ]);
    }

    /**
     * Verify password based on EE version
     */
    private function verify_password($password, $hashed_password, $salt)
    {
        if (strpos($hashed_password, '$2y$') === 0 || strpos($hashed_password, '$2a$') === 0) {
            return password_verify($password, $hashed_password);
        }
        
        if (strlen($hashed_password) === 128) {
            return hash_equals($hashed_password, hash('sha512', $salt . $password));
        }
        
        if (strlen($hashed_password) === 40) {
            return hash_equals($hashed_password, sha1($salt . $password));
        }
        
        return hash_equals($hashed_password, hash('sha512', $salt . $password));
    }

    /**
     * Handle categories request
     */
    private function handle_categories_request()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->send_json_response(['error' => 'Method not allowed'], 405);
        }

        $token = $this->get_bearer_token();
        if (!$token || !$this->verify_token($token)) {
            $this->send_json_response(['error' => 'Unauthorized'], 401);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        
        $group_id = isset($input['group_id']) ? (int)$input['group_id'] : null;
        $cat_id = isset($input['cat_id']) ? (int)$input['cat_id'] : null;
        $parent_id = isset($input['parent_id']) ? (int)$input['parent_id'] : null;

        // Kategori sorgusu
        ee()->db->select('c.cat_id, c.cat_name, c.cat_url_title, c.cat_description, c.cat_image, c.cat_order, c.parent_id, cg.group_name, cg.group_id')
            ->from('categories c')
            ->join('category_groups cg', 'c.group_id = cg.group_id', 'left');

        // Filtreleme
        if ($cat_id) {
            ee()->db->where('c.cat_id', $cat_id);
        }
        
        if ($group_id) {
            ee()->db->where('c.group_id', $group_id);
        }
        
        if ($parent_id !== null) {
            ee()->db->where('c.parent_id', $parent_id);
        }

        ee()->db->order_by('c.cat_order', 'asc');
        
        $query = ee()->db->get();

        $categories = array();
        
        foreach ($query->result() as $cat) {
            $cat_data = array(
                'cat_id' => $cat->cat_id,
                'cat_name' => $cat->cat_name,
                'cat_url_title' => $cat->cat_url_title,
                'cat_order' => $cat->cat_order,
                'parent_id' => $cat->parent_id,
                'group' => array(
                    'group_id' => $cat->group_id,
                    'group_name' => $cat->group_name
                )
            );
            
            if (!empty($cat->cat_description)) {
                $cat_data['cat_description'] = $cat->cat_description;
            }
            
            if (!empty($cat->cat_image)) {
                $cat_data['cat_image'] = $this->parse_file_field($cat->cat_image);
            }
            
            $categories[] = $cat_data;
        }

        $this->send_json_response([
            'success' => true,
            'data' => $categories,
            'total' => count($categories)
        ]);
    }

    /**
     * Handle entries request
     */
    private function handle_entries_request()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->send_json_response(['error' => 'Method not allowed'], 405);
        }

        $token = $this->get_bearer_token();
        if (!$token || !$this->verify_token($token)) {
            $this->send_json_response(['error' => 'Unauthorized'], 401);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        
        $channel = isset($input['channel']) ? $input['channel'] : null;
        $limit = isset($input['limit']) ? (int)$input['limit'] : 10;
        $offset = isset($input['offset']) ? (int)$input['offset'] : 0;
        $status = isset($input['status']) ? $input['status'] : 'open';
        $order_by = isset($input['order_by']) ? $input['order_by'] : 'entry_date';
        $sort = isset($input['sort']) ? $input['sort'] : 'desc';
        $entry_id = isset($input['entry_id']) ? (int)$input['entry_id'] : null;
        $category_id = isset($input['category_id']) ? (int)$input['category_id'] : null;

        if (!$channel) {
            $this->send_json_response(['error' => 'Channel parameter required'], 400);
        }

        $limit = min($limit, 100);

        $channel_data = ee()->db->select('channel_id, channel_name')
            ->where('channel_name', $channel)
            ->get('channels')
            ->row();

        if (!$channel_data) {
            $this->send_json_response(['error' => 'Channel not found'], 404);
        }

        // Entry'leri getir
        ee()->db->select('ct.entry_id, ct.channel_id, ct.title, ct.url_title, ct.entry_date, ct.status, ct.author_id, m.username as author_username')
            ->from('channel_titles ct')
            ->join('members m', 'ct.author_id = m.member_id', 'left')
            ->where('ct.channel_id', $channel_data->channel_id)
            ->where('ct.status', $status);
        
        if ($entry_id) {
            ee()->db->where('ct.entry_id', $entry_id);
        }
        
        // Kategori filtresi
        if ($category_id) {
            ee()->db->join('category_posts cp', 'ct.entry_id = cp.entry_id', 'left');
            ee()->db->where('cp.cat_id', $category_id);
        }
        
        ee()->db->order_by($order_by, $sort)
            ->limit($limit, $offset);

        $query = ee()->db->get();

        $entries = array();
        
        // Tüm channel field'larını çek
        $all_fields = ee()->db->select('field_id, field_name, field_label, field_type')
            ->get('channel_fields')
            ->result();
        
        $field_map = array();
        foreach ($all_fields as $field) {
            $field_map[$field->field_id] = array(
                'name' => $field->field_name,
                'label' => $field->field_label,
                'type' => $field->field_type
            );
        }
        
        foreach ($query->result() as $row) {
            $fields = array();
            
            // Her field için veri çek
            foreach ($field_map as $field_id => $field_info) {
                $value = null;
                
                // Grid veya File Grid field'lar için direkt grid tablosuna bak
                if ($field_info['type'] == 'file_grid' || $field_info['type'] == 'grid') {
                    $grid_value = $this->parse_file_grid_field($field_id, $row->entry_id);
                    if ($grid_value !== null) {
                        $value = $grid_value;
                    } else {
                        $grid_value = $this->parse_grid_field($field_id, $row->entry_id);
                        if (!empty($grid_value)) {
                            $value = $grid_value;
                        }
                    }
                } else {
                    // Normal field'lar için veri çek
                    $field_table_name = 'channel_data_field_' . $field_id;
                    
                    if (ee()->db->table_exists($field_table_name)) {
                        $field_value = ee()->db->select('field_id_' . $field_id)
                            ->where('entry_id', $row->entry_id)
                            ->get($field_table_name)
                            ->row();
                        
                        if ($field_value && isset($field_value->{'field_id_' . $field_id})) {
                            $value = $field_value->{'field_id_' . $field_id};
                        }
                    }
                    
                    // File field ise parse et
                    if ($field_info['type'] == 'file' && !empty($value)) {
                        $value = $this->parse_file_field($value);
                    }
                }
                
                // Boş değilse field'ı ekle
                if ($value !== null && $value !== '') {
                    $fields[$field_info['name']] = $value;
                }
            }

            // Entry kategorilerini al
            $categories = $this->get_entry_categories($row->entry_id);

            $entry_data = array(
                'entry_id' => $row->entry_id,
                'title' => $row->title,
                'url_title' => $row->url_title,
                'entry_date' => $row->entry_date,
                'status' => $row->status,
                'author' => array(
                    'id' => $row->author_id,
                    'username' => $row->author_username
                ),
                'fields' => $fields
            );

            // Kategori varsa ekle
            if ($categories) {
                $entry_data['categories'] = $categories;
            }

            $entries[] = $entry_data;
        }

        // Toplam entry sayısı
        ee()->db->where('channel_id', $channel_data->channel_id)
            ->where('status', $status);
        
        if ($entry_id) {
            ee()->db->where('entry_id', $entry_id);
        }
        
        if ($category_id) {
            ee()->db->join('category_posts cp', 'channel_titles.entry_id = cp.entry_id', 'left');
            ee()->db->where('cp.cat_id', $category_id);
        }
        
        $total = ee()->db->count_all_results('channel_titles');

        $this->send_json_response([
            'success' => true,
            'data' => $entries,
            'pagination' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $total
            ]
        ]);
    }

    /**
     * Generate authentication token
     */
    private function generate_token($member_id)
    {
        $token = bin2hex(random_bytes(32));
        $expires = time() + 86400;

        $data = array(
            'member_id' => $member_id,
            'token' => hash('sha256', $token),
            'expires' => $expires,
            'created' => time()
        );

        ee()->db->where('expires <', time())
            ->delete('api_tokens');

        ee()->db->insert('api_tokens', $data);

        return $token;
    }

    /**
     * Verify authentication token
     */
    private function verify_token($token)
    {
        $hashed = hash('sha256', $token);
        
        $query = ee()->db->select('member_id, expires')
            ->where('token', $hashed)
            ->where('expires >', time())
            ->get('api_tokens');

        return $query->num_rows() > 0;
    }

    /**
     * Get bearer token from header
     */
    private function get_bearer_token()
    {
        $headers = $this->get_authorization_header();
        
        if (!empty($headers)) {
            if (preg_match('/Bearer\s+(.*)$/i', $headers, $matches)) {
                return $matches[1];
            }
        }
        return null;
    }

    /**
     * Get authorization header
     */
    private function get_authorization_header()
    {
        $headers = null;
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER['Authorization']);
        } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }
        return $headers;
    }

    /**
     * Send JSON response and exit
     */
    private function send_json_response($data, $status_code = 200)
    {
        http_response_code($status_code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}

// EOF