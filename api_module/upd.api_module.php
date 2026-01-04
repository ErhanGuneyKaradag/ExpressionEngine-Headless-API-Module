<?php

class Api_module_upd
{
    public $version = '1.0.0';

    /**
     * Install module
     */
    public function install()
    {
        // Module'ü kaydet
        $data = array(
            'module_name'        => 'Api_module',
            'module_version'     => $this->version,
            'has_cp_backend'     => 'n',
            'has_publish_fields' => 'n'
        );

        ee()->db->insert('modules', $data);

        // Token tablosunu oluştur
        ee()->load->dbforge();
        
        $fields = array(
            'token_id' => array(
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => TRUE,
                'auto_increment' => TRUE
            ),
            'member_id' => array(
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => TRUE
            ),
            'token' => array(
                'type'       => 'VARCHAR',
                'constraint' => 64
            ),
            'expires' => array(
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => TRUE
            ),
            'created' => array(
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => TRUE
            )
        );

        ee()->dbforge->add_field($fields);
        ee()->dbforge->add_key('token_id', TRUE);
        ee()->dbforge->add_key('token');
        ee()->dbforge->add_key('member_id');
        ee()->dbforge->add_key('expires');
        ee()->dbforge->create_table('api_tokens');

        return TRUE;
    }

    /**
     * Uninstall module
     */
    public function uninstall()
    {
        // Module'ü sil
        ee()->db->where('module_name', 'Api_module')
            ->delete('modules');

        // Token tablosunu sil
        ee()->load->dbforge();
        ee()->dbforge->drop_table('api_tokens');

        return TRUE;
    }

    /**
     * Update module
     */
    public function update($current = '')
    {
        if ($current == $this->version) {
            return FALSE;
        }

        return TRUE;
    }
}