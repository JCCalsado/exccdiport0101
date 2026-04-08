<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EnhancedSubjectSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('student_enrollments')->delete();
        DB::table('subjects')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $now = now();
        $rate = 364.00;
        $labFee = 1656.00;

        $subjects = [

            // =====================================================================
            // BS INFORMATION TECHNOLOGY
            // =====================================================================

            // 1st Year — 1st Sem
            ['code'=>'IT-ENG1',      'name'=>'Purposive Communication',           'units'=>3, 'has_lab'=>false, 'year_level'=>'1st Year','semester'=>'1st Sem','course'=>'BS Information Technology'],
            ['code'=>'IT-GEELEC1',   'name'=>'Living in the IT Era',               'units'=>3, 'has_lab'=>false, 'year_level'=>'1st Year','semester'=>'1st Sem','course'=>'BS Information Technology'],
            ['code'=>'IT-MATH1',     'name'=>'Mathematics in the Modern World',    'units'=>3, 'has_lab'=>false, 'year_level'=>'1st Year','semester'=>'1st Sem','course'=>'BS Information Technology'],
            ['code'=>'IT-ITC101',    'name'=>'Introduction to Computing',           'units'=>3, 'has_lab'=>false, 'year_level'=>'1st Year','semester'=>'1st Sem','course'=>'BS Information Technology'],
            ['code'=>'IT-ITC102',    'name'=>'Computer Programming 1',             'units'=>3, 'has_lab'=>true,  'year_level'=>'1st Year','semester'=>'1st Sem','course'=>'BS Information Technology'],
            ['code'=>'IT-ITC103',    'name'=>'IT Software Solutions for Business', 'units'=>3, 'has_lab'=>true,  'year_level'=>'1st Year','semester'=>'1st Sem','course'=>'BS Information Technology'],

            // 2nd Year — 1st Sem (BSIT 2A/2B/2C/2D share same subjects)
            ['code'=>'IT-HIST1',     'name'=>'Readings in the Philippine History', 'units'=>3, 'has_lab'=>false, 'year_level'=>'2nd Year','semester'=>'1st Sem','course'=>'BS Information Technology'],
            ['code'=>'IT-PHILO1',    'name'=>'Understanding the Self',             'units'=>3, 'has_lab'=>false, 'year_level'=>'2nd Year','semester'=>'1st Sem','course'=>'BS Information Technology'],
            ['code'=>'IT-GEELEC3',   'name'=>'Philippine Indigenous Communities', 'units'=>3, 'has_lab'=>false, 'year_level'=>'2nd Year','semester'=>'1st Sem','course'=>'BS Information Technology'],
            ['code'=>'IT-ITC201',    'name'=>'Data Structures and Algorithms',     'units'=>3, 'has_lab'=>true,  'year_level'=>'2nd Year','semester'=>'1st Sem','course'=>'BS Information Technology'],
            ['code'=>'IT-ITP202',    'name'=>'Application Development and Emerging Technologies','units'=>3,'has_lab'=>true,'year_level'=>'2nd Year','semester'=>'1st Sem','course'=>'BS Information Technology'],
            ['code'=>'IT-ITP203',    'name'=>'Fundamentals of Database Systems',   'units'=>3, 'has_lab'=>true,  'year_level'=>'2nd Year','semester'=>'1st Sem','course'=>'BS Information Technology'],

            // 3rd Year — 1st Sem (BSIT 3A/3B/3C share same subjects)
            ['code'=>'IT-ENG3',      'name'=>'Research Production',                'units'=>3, 'has_lab'=>false, 'year_level'=>'3rd Year','semester'=>'1st Sem','course'=>'BS Information Technology'],
            ['code'=>'IT-ACCTNG',    'name'=>'Fundamentals of Accounting',         'units'=>3, 'has_lab'=>false, 'year_level'=>'3rd Year','semester'=>'1st Sem','course'=>'BS Information Technology'],
            ['code'=>'IT-ITP306',    'name'=>'Networking 2',                       'units'=>3, 'has_lab'=>true,  'year_level'=>'3rd Year','semester'=>'1st Sem','course'=>'BS Information Technology'],
            ['code'=>'IT-ITP302',    'name'=>'Systems Integration & Architecture 1','units'=>3,'has_lab'=>false,'year_level'=>'3rd Year','semester'=>'1st Sem','course'=>'BS Information Technology'],
            ['code'=>'IT-ITELEC3',   'name'=>'Web Systems and Technologies',       'units'=>3, 'has_lab'=>true,  'year_level'=>'3rd Year','semester'=>'1st Sem','course'=>'BS Information Technology'],
            ['code'=>'IT-ITP304',    'name'=>'Information Assurance and Security 1','units'=>3,'has_lab'=>false,'year_level'=>'3rd Year','semester'=>'1st Sem','course'=>'BS Information Technology'],

            // 4th Year — 1st Sem
            ['code'=>'IT-ITP402',    'name'=>'Systems Administration and Maintenance','units'=>3,'has_lab'=>true,'year_level'=>'4th Year','semester'=>'1st Sem','course'=>'BS Information Technology'],
            ['code'=>'IT-PROJECT2',  'name'=>'Capstone Project 2',                 'units'=>3, 'has_lab'=>false, 'year_level'=>'4th Year','semester'=>'1st Sem','course'=>'BS Information Technology'],

            // =====================================================================
            // BS COMPUTER SCIENCE
            // =====================================================================

            // 1st Year — 1st Sem
            ['code'=>'CS-ENG1',      'name'=>'Purposive Communication',            'units'=>3, 'has_lab'=>false, 'year_level'=>'1st Year','semester'=>'1st Sem','course'=>'BS Computer Science'],
            ['code'=>'CS-GEELEC1',   'name'=>'Living in the IT Era',               'units'=>3, 'has_lab'=>false, 'year_level'=>'1st Year','semester'=>'1st Sem','course'=>'BS Computer Science'],
            ['code'=>'CS-MATH1',     'name'=>'Mathematics in the Modern World',    'units'=>3, 'has_lab'=>false, 'year_level'=>'1st Year','semester'=>'1st Sem','course'=>'BS Computer Science'],
            ['code'=>'CS-ITC101',    'name'=>'Introduction to Computing',           'units'=>3, 'has_lab'=>false, 'year_level'=>'1st Year','semester'=>'1st Sem','course'=>'BS Computer Science'],
            ['code'=>'CS-ITC102',    'name'=>'Fundamentals of Programming',        'units'=>3, 'has_lab'=>true,  'year_level'=>'1st Year','semester'=>'1st Sem','course'=>'BS Computer Science'],
            ['code'=>'CS-ITC103',    'name'=>'IT Software Solutions for Business', 'units'=>3, 'has_lab'=>true,  'year_level'=>'1st Year','semester'=>'1st Sem','course'=>'BS Computer Science'],

            // 2nd Year — 1st Sem
            ['code'=>'CS-ETHICS',    'name'=>'Ethics',                             'units'=>3, 'has_lab'=>false, 'year_level'=>'2nd Year','semester'=>'1st Sem','course'=>'BS Computer Science'],
            ['code'=>'CS-MATH2',     'name'=>'Probability and Statistics',         'units'=>3, 'has_lab'=>false, 'year_level'=>'2nd Year','semester'=>'1st Sem','course'=>'BS Computer Science'],
            ['code'=>'CS-HIST1',     'name'=>'Reading in the Philippine History',  'units'=>3, 'has_lab'=>false, 'year_level'=>'2nd Year','semester'=>'1st Sem','course'=>'BS Computer Science'],
            ['code'=>'CS-GEELEC3',   'name'=>'Philippine Indigenous Communities', 'units'=>3, 'has_lab'=>false, 'year_level'=>'2nd Year','semester'=>'1st Sem','course'=>'BS Computer Science'],
            ['code'=>'CS-CSC201',    'name'=>'Data Structure and Algorithms',      'units'=>3, 'has_lab'=>true,  'year_level'=>'2nd Year','semester'=>'1st Sem','course'=>'BS Computer Science'],
            ['code'=>'CS-CSC202',    'name'=>'Application Development and Emerging Technologies','units'=>3,'has_lab'=>true,'year_level'=>'2nd Year','semester'=>'1st Sem','course'=>'BS Computer Science'],
            ['code'=>'CS-CSC203',    'name'=>'Discrete Structures 1',              'units'=>3, 'has_lab'=>false, 'year_level'=>'2nd Year','semester'=>'1st Sem','course'=>'BS Computer Science'],

            // 3rd Year — 1st Sem
            ['code'=>'CS-RESEARCH',  'name'=>'Research Production',                'units'=>3, 'has_lab'=>false, 'year_level'=>'3rd Year','semester'=>'1st Sem','course'=>'BS Computer Science'],
            ['code'=>'CS-CSP301',    'name'=>'Automata Theory and Formal Languages','units'=>3,'has_lab'=>false,'year_level'=>'3rd Year','semester'=>'1st Sem','course'=>'BS Computer Science'],
            ['code'=>'CS-CSP302',    'name'=>'Architecture and Organization',      'units'=>3, 'has_lab'=>false, 'year_level'=>'3rd Year','semester'=>'1st Sem','course'=>'BS Computer Science'],
            ['code'=>'CS-CSP303',    'name'=>'Information Assurance and Security', 'units'=>3, 'has_lab'=>false, 'year_level'=>'3rd Year','semester'=>'1st Sem','course'=>'BS Computer Science'],
            ['code'=>'CS-CSC304',    'name'=>'Application Development and Emerging Technologies','units'=>3,'has_lab'=>true,'year_level'=>'3rd Year','semester'=>'1st Sem','course'=>'BS Computer Science'],
            ['code'=>'CS-CSP305',    'name'=>'Software Engineering 1',             'units'=>3, 'has_lab'=>false, 'year_level'=>'3rd Year','semester'=>'1st Sem','course'=>'BS Computer Science'],
            ['code'=>'CS-CSP306',    'name'=>'Networks and Communications',        'units'=>3, 'has_lab'=>false, 'year_level'=>'3rd Year','semester'=>'1st Sem','course'=>'BS Computer Science'],

            // 4th Year — 1st Sem
            ['code'=>'CS-CSP402',    'name'=>'Systems Fundamentals',               'units'=>3, 'has_lab'=>false, 'year_level'=>'4th Year','semester'=>'1st Sem','course'=>'BS Computer Science'],
            ['code'=>'CS-CSP403',    'name'=>'Human Computer Interaction',         'units'=>3, 'has_lab'=>true,  'year_level'=>'4th Year','semester'=>'1st Sem','course'=>'BS Computer Science'],
            ['code'=>'CS-THESIS2',   'name'=>'CS Thesis 2',                        'units'=>3, 'has_lab'=>false, 'year_level'=>'4th Year','semester'=>'1st Sem','course'=>'BS Computer Science'],

            // =====================================================================
            // BS INFORMATION SYSTEMS
            // =====================================================================

            // 1st Year — 1st Sem
            ['code'=>'IS-ENG1',      'name'=>'Purposive Communication',            'units'=>3, 'has_lab'=>false, 'year_level'=>'1st Year','semester'=>'1st Sem','course'=>'BS Information Systems'],
            ['code'=>'IS-GEELEC1',   'name'=>'Living in the IT Era',               'units'=>3, 'has_lab'=>false, 'year_level'=>'1st Year','semester'=>'1st Sem','course'=>'BS Information Systems'],
            ['code'=>'IS-MATH1',     'name'=>'Mathematics in the Modern World',    'units'=>3, 'has_lab'=>false, 'year_level'=>'1st Year','semester'=>'1st Sem','course'=>'BS Information Systems'],
            ['code'=>'IS-ITC101',    'name'=>'Introduction to Computing',           'units'=>3, 'has_lab'=>false, 'year_level'=>'1st Year','semester'=>'1st Sem','course'=>'BS Information Systems'],
            ['code'=>'IS-ITC102',    'name'=>'Computer Programming 1',             'units'=>3, 'has_lab'=>true,  'year_level'=>'1st Year','semester'=>'1st Sem','course'=>'BS Information Systems'],
            ['code'=>'IS-ITC103',    'name'=>'IT Software Solutions for Business', 'units'=>3, 'has_lab'=>true,  'year_level'=>'1st Year','semester'=>'1st Sem','course'=>'BS Information Systems'],

            // 2nd Year — 1st Sem
            ['code'=>'IS-HIST1',     'name'=>'Readings in the Philippine History', 'units'=>3, 'has_lab'=>false, 'year_level'=>'2nd Year','semester'=>'1st Sem','course'=>'BS Information Systems'],
            ['code'=>'IS-ETHICS',    'name'=>'Ethics',                             'units'=>3, 'has_lab'=>false, 'year_level'=>'2nd Year','semester'=>'1st Sem','course'=>'BS Information Systems'],
            ['code'=>'IS-GEELEC3',   'name'=>'Philippine Indigenous Communities', 'units'=>3, 'has_lab'=>false, 'year_level'=>'2nd Year','semester'=>'1st Sem','course'=>'BS Information Systems'],
            ['code'=>'IS-ISC201',    'name'=>'Data Structures and Algorithms',     'units'=>3, 'has_lab'=>true,  'year_level'=>'2nd Year','semester'=>'1st Sem','course'=>'BS Information Systems'],
            ['code'=>'IS-ISC202',    'name'=>'Application Development and Emerging Technologies','units'=>3,'has_lab'=>true,'year_level'=>'2nd Year','semester'=>'1st Sem','course'=>'BS Information Systems'],
            ['code'=>'IS-ISC203',    'name'=>'Organization and Management Concepts','units'=>3,'has_lab'=>false,'year_level'=>'2nd Year','semester'=>'1st Sem','course'=>'BS Information Systems'],

            // 3rd Year — 1st Sem
            ['code'=>'IS-RESEARCH',  'name'=>'Research Production',                'units'=>3, 'has_lab'=>false, 'year_level'=>'3rd Year','semester'=>'1st Sem','course'=>'BS Information Systems'],
            ['code'=>'IS-ISP301',    'name'=>'Enterprise Architecture',            'units'=>3, 'has_lab'=>false, 'year_level'=>'3rd Year','semester'=>'1st Sem','course'=>'BS Information Systems'],
            ['code'=>'IS-ISP302',    'name'=>'Business Process Management',        'units'=>3, 'has_lab'=>false, 'year_level'=>'3rd Year','semester'=>'1st Sem','course'=>'BS Information Systems'],
            ['code'=>'IS-ISP303',    'name'=>'Quantitative Methods',               'units'=>3, 'has_lab'=>false, 'year_level'=>'3rd Year','semester'=>'1st Sem','course'=>'BS Information Systems'],
            ['code'=>'IS-ELECT1',    'name'=>'IT Security Management',             'units'=>3, 'has_lab'=>false, 'year_level'=>'3rd Year','semester'=>'1st Sem','course'=>'BS Information Systems'],
            ['code'=>'IS-ISP306',    'name'=>'Financial Management',               'units'=>3, 'has_lab'=>false, 'year_level'=>'3rd Year','semester'=>'1st Sem','course'=>'BS Information Systems'],

            // 4th Year — 1st Sem
            ['code'=>'IS-ELECT1-4',  'name'=>'Data Mining',                        'units'=>3, 'has_lab'=>true,  'year_level'=>'4th Year','semester'=>'1st Sem','course'=>'BS Information Systems'],
            ['code'=>'IS-PROJECT2',  'name'=>'Capstone Project 2',                 'units'=>3, 'has_lab'=>false, 'year_level'=>'4th Year','semester'=>'1st Sem','course'=>'BS Information Systems'],
            ['code'=>'IS-GEELEC3-4', 'name'=>'Philippine Indigenous Communities (Senior)','units'=>3,'has_lab'=>false,'year_level'=>'4th Year','semester'=>'1st Sem','course'=>'BS Information Systems'],

            // =====================================================================
            // ASSOCIATE IN COMPUTER TECHNOLOGY — PROGRAMMING
            // =====================================================================

            // 1st Year — 1st Sem
            ['code'=>'ACP-ENG1',     'name'=>'Purposive Communication',            'units'=>3, 'has_lab'=>false, 'year_level'=>'1st Year','semester'=>'1st Sem','course'=>'Associate in Computer Technology - Programming'],
            ['code'=>'ACP-PHILO1',   'name'=>'Understanding the Self',             'units'=>3, 'has_lab'=>false, 'year_level'=>'1st Year','semester'=>'1st Sem','course'=>'Associate in Computer Technology - Programming'],
            ['code'=>'ACP-MATH1',    'name'=>'Mathematics in the Modern World',    'units'=>3, 'has_lab'=>false, 'year_level'=>'1st Year','semester'=>'1st Sem','course'=>'Associate in Computer Technology - Programming'],
            ['code'=>'ACP-ITC101',   'name'=>'Introduction to Computing',           'units'=>3, 'has_lab'=>false, 'year_level'=>'1st Year','semester'=>'1st Sem','course'=>'Associate in Computer Technology - Programming'],
            ['code'=>'ACP-ITC102',   'name'=>'Computer Programming 1',             'units'=>3, 'has_lab'=>true,  'year_level'=>'1st Year','semester'=>'1st Sem','course'=>'Associate in Computer Technology - Programming'],
            ['code'=>'ACP-ITC103',   'name'=>'IT Software Solutions for Business', 'units'=>3, 'has_lab'=>true,  'year_level'=>'1st Year','semester'=>'1st Sem','course'=>'Associate in Computer Technology - Programming'],

            // 2nd Year — 1st Sem
            ['code'=>'ACP-ETHICS',   'name'=>'Ethics',                             'units'=>3, 'has_lab'=>false, 'year_level'=>'2nd Year','semester'=>'1st Sem','course'=>'Associate in Computer Technology - Programming'],
            ['code'=>'ACP-HIST1',    'name'=>'Readings in Philippine History',     'units'=>3, 'has_lab'=>false, 'year_level'=>'2nd Year','semester'=>'1st Sem','course'=>'Associate in Computer Technology - Programming'],
            ['code'=>'ACP-SCIE1',    'name'=>'Science, Technology & Society',      'units'=>3, 'has_lab'=>false, 'year_level'=>'2nd Year','semester'=>'1st Sem','course'=>'Associate in Computer Technology - Programming'],
            ['code'=>'ACP-GEELEC3',  'name'=>'Philippine Indigenous Community',    'units'=>3, 'has_lab'=>false, 'year_level'=>'2nd Year','semester'=>'1st Sem','course'=>'Associate in Computer Technology - Programming'],
            ['code'=>'ACP-HUMAN',    'name'=>'Art Appreciation',                   'units'=>3, 'has_lab'=>false, 'year_level'=>'2nd Year','semester'=>'1st Sem','course'=>'Associate in Computer Technology - Programming'],
            ['code'=>'ACP-ITC201',   'name'=>'Data Structure and Algorithms',      'units'=>3, 'has_lab'=>true,  'year_level'=>'2nd Year','semester'=>'1st Sem','course'=>'Associate in Computer Technology - Programming'],
            ['code'=>'ACP-ELEC2',    'name'=>'Platform Technologies',              'units'=>3, 'has_lab'=>true,  'year_level'=>'2nd Year','semester'=>'1st Sem','course'=>'Associate in Computer Technology - Programming'],
            ['code'=>'ACP-ELEC3',    'name'=>'Web Development & Programming 1',    'units'=>3, 'has_lab'=>true,  'year_level'=>'2nd Year','semester'=>'1st Sem','course'=>'Associate in Computer Technology - Programming'],

            // =====================================================================
            // ASSOCIATE IN COMPUTER TECHNOLOGY — MULTIMEDIA/ANIMATION
            // =====================================================================

            // 1st Year — 1st Sem
            ['code'=>'ACM-ENG1',     'name'=>'Purposive Communication',            'units'=>3, 'has_lab'=>false, 'year_level'=>'1st Year','semester'=>'1st Sem','course'=>'Associate in Computer Technology - Multimedia/Animation'],
            ['code'=>'ACM-PHILO1',   'name'=>'Understanding the Self',             'units'=>3, 'has_lab'=>false, 'year_level'=>'1st Year','semester'=>'1st Sem','course'=>'Associate in Computer Technology - Multimedia/Animation'],
            ['code'=>'ACM-MATH1',    'name'=>'Mathematics in the Modern World',    'units'=>3, 'has_lab'=>false, 'year_level'=>'1st Year','semester'=>'1st Sem','course'=>'Associate in Computer Technology - Multimedia/Animation'],
            ['code'=>'ACM-ITC101',   'name'=>'Introduction to Computing',           'units'=>3, 'has_lab'=>false, 'year_level'=>'1st Year','semester'=>'1st Sem','course'=>'Associate in Computer Technology - Multimedia/Animation'],
            ['code'=>'ACM-ITC102',   'name'=>'IT Software Solutions for Business 1','units'=>3,'has_lab'=>true,  'year_level'=>'1st Year','semester'=>'1st Sem','course'=>'Associate in Computer Technology - Multimedia/Animation'],
            ['code'=>'ACM-ITC103',   'name'=>'Computer Programming 1',             'units'=>3, 'has_lab'=>true,  'year_level'=>'1st Year','semester'=>'1st Sem','course'=>'Associate in Computer Technology - Multimedia/Animation'],
            ['code'=>'ACM-ELEC1',    'name'=>'Freehand and Digital Drawing',       'units'=>3, 'has_lab'=>true,  'year_level'=>'1st Year','semester'=>'1st Sem','course'=>'Associate in Computer Technology - Multimedia/Animation'],

            // 2nd Year — 1st Sem
            ['code'=>'ACM-ETHICS',   'name'=>'Ethics',                             'units'=>3, 'has_lab'=>false, 'year_level'=>'2nd Year','semester'=>'1st Sem','course'=>'Associate in Computer Technology - Multimedia/Animation'],
            ['code'=>'ACM-HIST1',    'name'=>'Readings in Philippine History',     'units'=>3, 'has_lab'=>false, 'year_level'=>'2nd Year','semester'=>'1st Sem','course'=>'Associate in Computer Technology - Multimedia/Animation'],
            ['code'=>'ACM-SCIE1',    'name'=>'Science, Technology & Society',      'units'=>3, 'has_lab'=>false, 'year_level'=>'2nd Year','semester'=>'1st Sem','course'=>'Associate in Computer Technology - Multimedia/Animation'],
            ['code'=>'ACM-GEELEC3',  'name'=>'Philippine Indigenous Community',    'units'=>3, 'has_lab'=>false, 'year_level'=>'2nd Year','semester'=>'1st Sem','course'=>'Associate in Computer Technology - Multimedia/Animation'],
            ['code'=>'ACM-HUMAN',    'name'=>'Art Appreciation',                   'units'=>3, 'has_lab'=>false, 'year_level'=>'2nd Year','semester'=>'1st Sem','course'=>'Associate in Computer Technology - Multimedia/Animation'],
            ['code'=>'ACM-ITC201',   'name'=>'Data Structure and Algorithms',      'units'=>3, 'has_lab'=>true,  'year_level'=>'2nd Year','semester'=>'1st Sem','course'=>'Associate in Computer Technology - Multimedia/Animation'],
            ['code'=>'ACM-ELEC3',    'name'=>'Website Design',                     'units'=>3, 'has_lab'=>true,  'year_level'=>'2nd Year','semester'=>'1st Sem','course'=>'Associate in Computer Technology - Multimedia/Animation'],
            ['code'=>'ACM-ELEC4',    'name'=>'Script Writing and Storyboard Design','units'=>3,'has_lab'=>false, 'year_level'=>'2nd Year','semester'=>'1st Sem','course'=>'Associate in Computer Technology - Multimedia/Animation'],

            // =====================================================================
            // ASSOCIATE IN COMPUTER TECHNOLOGY — NETWORKING
            // =====================================================================

            // 1st Year — 1st Sem
            ['code'=>'ACN-ENG1',     'name'=>'Purposive Communication',            'units'=>3, 'has_lab'=>false, 'year_level'=>'1st Year','semester'=>'1st Sem','course'=>'Associate in Computer Technology - Networking'],
            ['code'=>'ACN-PHILO1',   'name'=>'Understanding the Self',             'units'=>3, 'has_lab'=>false, 'year_level'=>'1st Year','semester'=>'1st Sem','course'=>'Associate in Computer Technology - Networking'],
            ['code'=>'ACN-MATH1',    'name'=>'Mathematics in the Modern World',    'units'=>3, 'has_lab'=>false, 'year_level'=>'1st Year','semester'=>'1st Sem','course'=>'Associate in Computer Technology - Networking'],
            ['code'=>'ACN-ITC101',   'name'=>'Introduction to Computing',           'units'=>3, 'has_lab'=>false, 'year_level'=>'1st Year','semester'=>'1st Sem','course'=>'Associate in Computer Technology - Networking'],
            ['code'=>'ACN-ITC102',   'name'=>'Computer Programming 1',             'units'=>3, 'has_lab'=>true,  'year_level'=>'1st Year','semester'=>'1st Sem','course'=>'Associate in Computer Technology - Networking'],
            ['code'=>'ACN-ITC103',   'name'=>'IT Software Solutions for Business 1','units'=>3,'has_lab'=>true,  'year_level'=>'1st Year','semester'=>'1st Sem','course'=>'Associate in Computer Technology - Networking'],

            // 2nd Year — 1st Sem
            ['code'=>'ACN-ETHICS',   'name'=>'Ethics',                             'units'=>3, 'has_lab'=>false, 'year_level'=>'2nd Year','semester'=>'1st Sem','course'=>'Associate in Computer Technology - Networking'],
            ['code'=>'ACN-HIST1',    'name'=>'Readings in Philippine History',     'units'=>3, 'has_lab'=>false, 'year_level'=>'2nd Year','semester'=>'1st Sem','course'=>'Associate in Computer Technology - Networking'],
            ['code'=>'ACN-SCIE1',    'name'=>'Science, Technology & Society',      'units'=>3, 'has_lab'=>false, 'year_level'=>'2nd Year','semester'=>'1st Sem','course'=>'Associate in Computer Technology - Networking'],
            ['code'=>'ACN-GEELEC3',  'name'=>'Philippine Indigenous Community',    'units'=>3, 'has_lab'=>false, 'year_level'=>'2nd Year','semester'=>'1st Sem','course'=>'Associate in Computer Technology - Networking'],
            ['code'=>'ACN-HUMAN',    'name'=>'Art Appreciation',                   'units'=>3, 'has_lab'=>false, 'year_level'=>'2nd Year','semester'=>'1st Sem','course'=>'Associate in Computer Technology - Networking'],
            ['code'=>'ACN-ITC201',   'name'=>'Data Structure and Algorithms',      'units'=>3, 'has_lab'=>true,  'year_level'=>'2nd Year','semester'=>'1st Sem','course'=>'Associate in Computer Technology - Networking'],
            ['code'=>'ACN-ELEC3',    'name'=>'Internet Protocol',                  'units'=>3, 'has_lab'=>false, 'year_level'=>'2nd Year','semester'=>'1st Sem','course'=>'Associate in Computer Technology - Networking'],
            ['code'=>'ACN-ELEC4',    'name'=>'Data Communication and Networking 2','units'=>3, 'has_lab'=>false, 'year_level'=>'2nd Year','semester'=>'1st Sem','course'=>'Associate in Computer Technology - Networking'],

            // =====================================================================
            // DIPLOMA IN SOFTWARE DEVELOPMENT AND PROGRAMMING
            // =====================================================================

            ['code'=>'DSD-RESEARCH', 'name'=>'Research Production',                'units'=>3, 'has_lab'=>false, 'year_level'=>'3rd Year','semester'=>'1st Sem','course'=>'Diploma in Software Development and Programming'],
            ['code'=>'DSD-ENTREP',   'name'=>'Fundamentals of Entrepreneurship',   'units'=>3, 'has_lab'=>false, 'year_level'=>'3rd Year','semester'=>'1st Sem','course'=>'Diploma in Software Development and Programming'],
            ['code'=>'DSD-ITP301',   'name'=>'Web Development & Programming 2',    'units'=>3, 'has_lab'=>true,  'year_level'=>'3rd Year','semester'=>'1st Sem','course'=>'Diploma in Software Development and Programming'],
            ['code'=>'DSD-ITP302',   'name'=>'Systems Integration & Architecture 1','units'=>3,'has_lab'=>false, 'year_level'=>'3rd Year','semester'=>'1st Sem','course'=>'Diploma in Software Development and Programming'],
            ['code'=>'DSD-ITP303',   'name'=>'Event Driven Programming (Game Development)','units'=>3,'has_lab'=>true,'year_level'=>'3rd Year','semester'=>'1st Sem','course'=>'Diploma in Software Development and Programming'],
            ['code'=>'DSD-ITP304',   'name'=>'Information Assurance & Security 1', 'units'=>3, 'has_lab'=>false, 'year_level'=>'3rd Year','semester'=>'1st Sem','course'=>'Diploma in Software Development and Programming'],
            ['code'=>'DSD-ITP305',   'name'=>'Social and Professional Issues',     'units'=>3, 'has_lab'=>false, 'year_level'=>'3rd Year','semester'=>'1st Sem','course'=>'Diploma in Software Development and Programming'],
            ['code'=>'DSD-ELEC1',    'name'=>'Intro to 2D Game Art Development',   'units'=>4, 'has_lab'=>true,  'year_level'=>'3rd Year','semester'=>'1st Sem','course'=>'Diploma in Software Development and Programming'],
            ['code'=>'DSD-ANIM3',    'name'=>'2D Animation Production',            'units'=>3, 'has_lab'=>true,  'year_level'=>'3rd Year','semester'=>'1st Sem','course'=>'Diploma in Software Development and Programming'],

            // =====================================================================
            // DIPLOMA IN ELECTRONICS AND COMPUTER TECHNOLOGY
            // =====================================================================

            ['code'=>'DEC-RESEARCH', 'name'=>'Research Production',                'units'=>3, 'has_lab'=>false, 'year_level'=>'3rd Year','semester'=>'1st Sem','course'=>'Diploma in Electronics and Computer Technology'],
            ['code'=>'DEC-ENTREP',   'name'=>'Fundamentals of Entrepreneurship',   'units'=>3, 'has_lab'=>false, 'year_level'=>'3rd Year','semester'=>'1st Sem','course'=>'Diploma in Electronics and Computer Technology'],
            ['code'=>'DEC-ETHICS',   'name'=>'Ethics',                             'units'=>3, 'has_lab'=>false, 'year_level'=>'3rd Year','semester'=>'1st Sem','course'=>'Diploma in Electronics and Computer Technology'],
            ['code'=>'DEC-ITP304',   'name'=>'Information Assurance & Security',   'units'=>3, 'has_lab'=>false, 'year_level'=>'3rd Year','semester'=>'1st Sem','course'=>'Diploma in Electronics and Computer Technology'],
            ['code'=>'DEC-PROG',     'name'=>'Computer Programming 2',             'units'=>3, 'has_lab'=>true,  'year_level'=>'3rd Year','semester'=>'1st Sem','course'=>'Diploma in Electronics and Computer Technology'],
            ['code'=>'DEC-ELEC3',    'name'=>'Consumer Electronics Servicing and Supervising','units'=>4,'has_lab'=>true,'year_level'=>'3rd Year','semester'=>'1st Sem','course'=>'Diploma in Electronics and Computer Technology'],
            ['code'=>'DEC-ELEC4',    'name'=>'PV System Diagnosis and Repair',     'units'=>3, 'has_lab'=>true,  'year_level'=>'3rd Year','semester'=>'1st Sem','course'=>'Diploma in Electronics and Computer Technology'],
            ['code'=>'DEC-ELEC5',    'name'=>'PV System Servicing and Operation',  'units'=>4, 'has_lab'=>true,  'year_level'=>'3rd Year','semester'=>'1st Sem','course'=>'Diploma in Electronics and Computer Technology'],

            // =====================================================================
            // BSEET (already in DB but included for completeness — will skip on unique conflict)
            // =====================================================================

            ['code'=>'GE-1',         'name'=>'Purposive Communication',            'units'=>3, 'has_lab'=>false, 'year_level'=>'1st Year','semester'=>'1st Sem','course'=>'BS Electrical Engineering Technology'],
            ['code'=>'GE-ELEC1',     'name'=>'Living in the IT Era',               'units'=>3, 'has_lab'=>true,  'year_level'=>'1st Year','semester'=>'1st Sem','course'=>'BS Electrical Engineering Technology'],
            ['code'=>'GE-2',         'name'=>'Mathematics in the Modern World',    'units'=>3, 'has_lab'=>false, 'year_level'=>'1st Year','semester'=>'1st Sem','course'=>'BS Electrical Engineering Technology'],
            ['code'=>'GE-3',         'name'=>'Science, Technology & Society',      'units'=>3, 'has_lab'=>false, 'year_level'=>'1st Year','semester'=>'1st Sem','course'=>'BS Electrical Engineering Technology'],
            ['code'=>'MATH-101',     'name'=>'Calculus 1 — Differential Calculus', 'units'=>3, 'has_lab'=>true,  'year_level'=>'1st Year','semester'=>'1st Sem','course'=>'BS Electrical Engineering Technology'],
            ['code'=>'PHYS-101',     'name'=>'Physics for Engineering Technologists','units'=>3,'has_lab'=>false,'year_level'=>'1st Year','semester'=>'1st Sem','course'=>'BS Electrical Engineering Technology'],
            ['code'=>'COMP-101',     'name'=>'Integrated Software Applications 1', 'units'=>3, 'has_lab'=>false, 'year_level'=>'1st Year','semester'=>'1st Sem','course'=>'BS Electrical Engineering Technology'],

            ['code'=>'GE-ELEC2',     'name'=>'Peace Studies and Education',        'units'=>3, 'has_lab'=>false, 'year_level'=>'1st Year','semester'=>'2nd Sem','course'=>'BS Electrical Engineering Technology'],
            ['code'=>'GE-4',         'name'=>'The Contemporary World',             'units'=>3, 'has_lab'=>false, 'year_level'=>'1st Year','semester'=>'2nd Sem','course'=>'BS Electrical Engineering Technology'],
            ['code'=>'MATH-102',     'name'=>'Calculus 2 — Integral Calculus',     'units'=>3, 'has_lab'=>true,  'year_level'=>'1st Year','semester'=>'2nd Sem','course'=>'BS Electrical Engineering Technology'],
            ['code'=>'CHEM-101',     'name'=>'Chemistry for Engineering Technologists','units'=>3,'has_lab'=>true,'year_level'=>'1st Year','semester'=>'2nd Sem','course'=>'BS Electrical Engineering Technology'],
            ['code'=>'FDB',          'name'=>'Fundamentals of Deformable Bodies',  'units'=>2, 'has_lab'=>true,  'year_level'=>'1st Year','semester'=>'2nd Sem','course'=>'BS Electrical Engineering Technology'],
            ['code'=>'COMP-102',     'name'=>'Integrated Software Applications 2', 'units'=>2, 'has_lab'=>false, 'year_level'=>'1st Year','semester'=>'2nd Sem','course'=>'BS Electrical Engineering Technology'],
            ['code'=>'CAD-1',        'name'=>'Computer-Aided Drafting',            'units'=>3, 'has_lab'=>true,  'year_level'=>'1st Year','semester'=>'2nd Sem','course'=>'BS Electrical Engineering Technology'],

        ];

        $rows = [];
        foreach ($subjects as $s) {
            $rows[] = [
                'code'           => $s['code'],
                'name'           => $s['name'],
                'units'          => $s['units'],
                'lec_units'      => $s['units'],
                'lab_units'      => $s['has_lab'] ? 1 : 0,
                'price_per_unit' => $rate,
                'has_lab'        => $s['has_lab'] ? 1 : 0,
                'lab_fee'        => $s['has_lab'] ? $labFee : 0.00,
                'year_level'     => $s['year_level'],
                'semester'       => $s['semester'],
                'course'         => $s['course'],
                'is_active'      => 1,
                'created_at'     => $now,
                'updated_at'     => $now,
            ];
        }

        // insertOrIgnore — safe to run multiple times, skips duplicate codes
        foreach (array_chunk($rows, 50) as $chunk) {
            DB::table('subjects')->insertOrIgnore($chunk);
        }

        $total = DB::table('subjects')->count();
        $this->command->info("Done. {$total} subjects in database.");
    }
}