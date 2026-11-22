<?php
namespace App\Controllers;

use App\Repositories\GuideRepository;

class GuideController
{
    private GuideRepository $r;

    public function __construct()
    {
        $this->r = new GuideRepository();
    }

    public function index(): void
    {
        \require_role('ADMIN', 'DIGITADOR', 'SUPERVISOR', 'GERENCIA');
        \sync_erp();
        \view('guias.index', ['rows' => $this->r->all()]);
    }

    public function create(): void
    {
        http_response_code(403);
        \view('errors.403');
        exit;
    }

    public function store(): void
    {
        http_response_code(403);
        \view('errors.403');
        exit;
    }

    public function changeStatus(): void
    {
        http_response_code(403);
        \view('errors.403');
        exit;
    }
}
