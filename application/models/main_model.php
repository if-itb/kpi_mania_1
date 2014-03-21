<?php
class Main_model extends CI_Model{
    function  __construct() {
        parent::__construct();
    }
		
		function check_login($email,$password){
			//cek apakah terdapat user dengan email yang diberikan
			//jika ada ambil id dan saltnya
			$this->db->select('id,password,salt');
			$this->db->where('email',$email);
			if($query = $this->db->get('login')->row()){
				if($query->password == $this->encrypt->sha1($password.$query->salt))
				{
					$this->sucess_attempt($email);
					$this->session->set_userdata('email',$email);		
					return 1;
				}else
				{	
					$this->failed_attempt($email);
					return 0;
				}	
			}else 
			{
				$this->failed_attempt($email);
				return 0;
			}
		}
		
		function check_attempt($email){
			//periksa percobaan login sebelumnya
			$this->db->select('id,attempt,last_attempt');
			$this->db->where('email',$email);	
			if($query = $this->db->get('login_attempt')->row())
			{
				//jika terdapat percobaan login sebelumnya periksa attempt
				if($query->attempt > 5){
					//jika attempt lebih besar dari 5 periksa last_attempt apakah sudah 5 menit
					if($this->db->query("SELECT timestamp(DATE_SUB(NOW(), INTERVAL 500 SECOND)) >= timestamp('".$query->last_attempt."') as result")->row()->result)
					{
						//jika sudah 5 menit clear attempt
						$this->sucess_attempt($email);
						return 1;	
					}else return 0; //jika belum 5 menit return false					
				}
				//jika attempt lebih kecil dari 5 return true
				return 1;	
			}else{
				$data = array(
					 'email' => $email 
				);
				$this->db->insert('login_attempt', $data); 
			}
			return 1; //jika tidak terdapat percobaan login return true
		}
		
		function failed_attempt($email){
			$this->db->where('email',$email);
			$this->db->set('attempt','attempt+1',FALSE);
			$this->db->set('last_attempt','now()',FALSE);
			$this->db->update('login_attempt');
		}
		
		function sucess_attempt($email){
			$this->db->where('email',$email);
			$this->db->set('attempt','0',FALSE);
			$this->db->set('last_attempt','now()',FALSE);
			$this->db->update('login_attempt');
		}
		
		function waiting_time($email){
			$this->db->select('last_attempt');
			$this->db->where('email',$email);	
			$last_attempt = $this->db->get('login_attempt')->row()->last_attempt;
			$waiting_time = $this->db->query("SELECT UNIX_TIMESTAMP(DATE_ADD('$last_attempt',INTERVAL 500 SECOND)) - UNIX_TIMESTAMP(NOW()) as waiting_time")->row()->waiting_time;
			return $waiting_time;
		}
		
		function get_folder($email){
			$this->db->select('id,salt');
			$this->db->where('email',$email);
			$query = $this->db->get('login')->row();
			return 'upload/'.$query->id.$query->salt;
		}
		
		function generate_forgot_token($email){
			$this->db->select('id,salt');
			$this->db->where('email',$email);
			$query = $this->db->get('login')->row();
			if($query){
				$this->db->where('email',$email);
				$this->db->delete('forgot_password');
				$token = $query->id.$this->encrypt->sha1(time().$query->salt);
				$data = array(
					 'email' => $email,
					 'token' => $token
				);
				$this->db->insert('forgot_password',$data);
				return $token;
			}
			return NULL;
		}
		
		function check_token($token){
			$this->db->select('id,time_stamp');
			$this->db->where('token',$token);
			if($query = $this->db->get('forgot_password')->row()){
				$waiting_time = $this->db->query("SELECT UNIX_TIMESTAMP(DATE_ADD('".$query->time_stamp."',INTERVAL 1 DAY)) - UNIX_TIMESTAMP(NOW()) as waiting_time")->row()->waiting_time;
				if($waiting_time > 0){
					return 1;
				}
				else{
					$this->db->where('id',$query->id);
					$this->db->delete('forgot_password');
					return 0;
				}
			}
			else{				
				return 0;
			}
		}
		
		function update_password($token,$password){
			$this->db->select('email');
			$this->db->where('token',$token);
			$email = $this->db->get('forgot_password')->row()->email;
			//delete token
			$this->db->where('email',$email);
			$this->db->delete('forgot_password');
			//update password
			$this->db->select('id,salt');
			$this->db->where('email',$email);
			$query = $this->db->get('login')->row();	
			$new_password = $this->encrypt->sha1($password.$query->salt);
			$this->db->where('email',$email);
			$this->db->set('password',$new_password);
			$this->db->update('login');
		}
		
}		
?>