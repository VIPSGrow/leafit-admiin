<?php

namespace App\Controllers\Admin;

use App\Services\utility\SlugService;

class SlugController extends Admin
{
    protected $user_details = null;
    protected SlugService $slugService;
    
    public function __construct()
    {
        helper('api');
        helper("function");
        helper('ResponceServices');
        $this->slugService = new SlugService();
    }
    
    public function category()
    {
        $db = \Config\Database::connect();
        $categories = $db->table('categories')
            ->select('id, name')
            ->where('slug','')->orWhere('slug',null)
            ->get()
            ->getResultArray();
            
        if (empty($categories)) {
            return response_helper('Category not found', true);
        }
        
        foreach($categories as $category){
            $slug = $this->slugService->generate(
                $category['name'],
                $category['name'],
                'categories',
                $category['id']
            );

            $db->table('categories')
                ->where('id', $category['id'])
                ->update(['slug' => $slug]);
        }
        
        return response_helper('Category slug generated successfully');
    }
    
    public function partner()
    {
        $db = \Config\Database::connect();
        $partners = $db->table('partner_details pd')
            ->select('pd.*,u.username,pd.slug as slug')
            ->join('users u', 'pd.partner_id = u.id')
            ->where('pd.slug','')->orWhere('pd.slug',null)
            ->get()
            ->getResultArray();
            
        if (empty($partners)) {
            return response_helper('Provider not found', true);
        }
        
        foreach($partners as $partner){
            $slug = $this->slugService->generate(
                $partner['username'],
                $partner['username'],
                'partner_details',
                $partner['partner_id'] ?? $partner['id'] ?? null
            );

            $db->table('partner_details')
                ->where('partner_id', $partner['id'])
                ->update(['slug' => $slug]);
        }
        return response_helper('Provider slug generated successfully');
    }

    public function service()
    {
        $db = \Config\Database::connect();
        $services = $db->table('services')
            ->where('slug','')->orWhere('slug',null)
            ->get()
            ->getResultArray();
            
        if (empty($services)) {
            return response_helper('Services not found', true);
        }
        
        foreach($services as $service){
            $slug = $this->slugService->generate(
                $service['title'],
                $service['title'],
                'services',
                $service['id']
            );

            $db->table('services')
                ->where('id', $service['id'])
                ->update(['slug' => $slug]);
        }
        return response_helper('Service slug generated successfully');
    }
} 