<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DivisionSkillSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'UI/UX Designer' => [
                'Figma', 'Adobe XD', 'Canva', 'Wireframing', 'Prototyping',
                'User Research', 'Usability Testing', 'Design System', 'Sketch', 'InVision'
            ],
            'Frontend Developer' => [
                'HTML', 'CSS', 'JavaScript', 'React.js', 'Vue.js',
                'TypeScript', 'Tailwind CSS', 'Bootstrap', 'Next.js', 'Git'
            ],
            'Backend Developer' => [
                'Laravel', 'Node.js', 'Express.js', 'Python', 'MySQL',
                'PostgreSQL', 'REST API', 'Docker', 'Redis', 'Git'
            ],
            'Mobile Developer' => [
                'Flutter', 'React Native', 'Kotlin', 'Swift', 'Dart',
                'Android Studio', 'Firebase', 'Xcode', 'REST API', 'Git'
            ],
            'Fullstack Developer' => [
                'JavaScript', 'React.js', 'Node.js', 'Laravel', 'MySQL',
                'MongoDB', 'REST API', 'Docker', 'Git', 'TypeScript'
            ],
            'Data Analyst' => [
                'Python', 'SQL', 'Excel', 'Tableau', 'Power BI',
                'Pandas', 'NumPy', 'Google Sheets', 'Data Visualization', 'Statistics'
            ],
            'Data Scientist' => [
                'Python', 'Machine Learning', 'TensorFlow', 'Scikit-learn', 'SQL',
                'Pandas', 'NumPy', 'Data Visualization', 'Statistics', 'Jupyter Notebook'
            ],
            'Machine Learning Engineer' => [
                'Python', 'TensorFlow', 'PyTorch', 'Scikit-learn', 'Deep Learning',
                'NLP', 'Computer Vision', 'Docker', 'MLflow', 'Kubernetes'
            ],
            'DevOps Engineer' => [
                'Docker', 'Kubernetes', 'CI/CD', 'Jenkins', 'Linux',
                'AWS', 'Terraform', 'Ansible', 'Monitoring', 'Git'
            ],
            'Cloud Engineer' => [
                'AWS', 'Google Cloud', 'Azure', 'Docker', 'Kubernetes',
                'Terraform', 'Linux', 'Networking', 'CI/CD', 'Security'
            ],
            'Cybersecurity Analyst' => [
                'Network Security', 'Ethical Hacking', 'Penetration Testing', 'Kali Linux', 'Wireshark',
                'Firewall', 'SIEM', 'Cryptography', 'Vulnerability Assessment', 'Python'
            ],
            'Database Administrator' => [
                'MySQL', 'PostgreSQL', 'Oracle', 'SQL Server', 'MongoDB',
                'Redis', 'Database Optimization', 'Backup & Recovery', 'SQL', 'Linux'
            ],
            'Game Developer' => [
                'Unity', 'Unreal Engine', 'C#', 'C++', 'Blender',
                '3D Modeling', 'Game Design', 'Physics Engine', 'Animation', 'Git'
            ],
            'Embedded Systems Engineer' => [
                'C', 'C++', 'Arduino', 'Raspberry Pi', 'IoT',
                'RTOS', 'Microcontroller', 'PCB Design', 'Python', 'Linux'
            ],
            'Network Engineer' => [
                'Cisco', 'Routing & Switching', 'TCP/IP', 'Firewall', 'VPN',
                'Linux', 'Network Monitoring', 'Wireshark', 'VLAN', 'BGP'
            ],
            'IT Support' => [
                'Windows Server', 'Linux', 'Networking', 'Troubleshooting', 'Active Directory',
                'Help Desk', 'Hardware', 'Virtualization', 'Office 365', 'ITIL'
            ],
            'Business Intelligence Developer' => [
                'Power BI', 'Tableau', 'SQL', 'ETL', 'Data Warehouse',
                'Excel', 'Python', 'SSRS', 'DAX', 'Data Modeling'
            ],
            'Quality Assurance Engineer' => [
                'Manual Testing', 'Selenium', 'Postman', 'Jira', 'Test Case Writing',
                'Regression Testing', 'API Testing', 'Cypress', 'Performance Testing', 'Agile'
            ],
            'Desain Grafis' => [
                'Graphic Design Fundamentals', 'Adobe Photoshop', 'Adobe Illustrator', 'Figma', 'Canva',
                'UI/UX Design', 'Typography', 'Color Theory', 'Branding', 'Layout Design', 'Vector Design'
            ],
            'Digital Marketing' => [
                'Digital Marketing Fundamentals', 'Social Media Management', 'Content Creation', 'SEO', 'Google Analytics',
                'Email Marketing', 'PPC Advertising', 'Copywriting', 'Brand Strategy', 'Market Research'
            ],
        ];

        foreach ($data as $divisionName => $skills) {
            // Insert atau ambil division
                $divisionId = DB::table('divisions')->insertGetId([
                'name'       => $divisionName,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($skills as $skillName) {
                // Insert skill kalau belum ada, lalu ambil ID-nya
                $skillId = DB::table('skills')->where('name', $skillName)->value('id');

                if (!$skillId) {
                    $skillId = DB::table('skills')->insertGetId([
                        'name'       => $skillName,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // Hubungkan division & skill
                DB::table('division_skills')->insertOrIgnore([
                    'division_id' => $divisionId,
                    'skill_id'    => $skillId,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }
        }
    }
}