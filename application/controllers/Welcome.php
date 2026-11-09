<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Controlador heredado de la instalación base de CodeIgniter.
 * Se conserva únicamente para redirigir al acceso autenticado y evitar
 * exponer la pantalla de bienvenida por defecto.
 */
class Welcome extends CI_Controller {

	public function index()
	{
		redirect('login');
	}
}
