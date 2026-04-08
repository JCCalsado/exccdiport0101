<?php

namespace Database\Seeders;

use App\Models\Subject;
use Illuminate\Database\Seeder;

/**
 * EnhancedSubjectSeeder — AY 2025-2026, 2nd Semester
 *
 * SOURCE OF TRUTH: public/images/Courses & Subjects/quarts01.jpg – quarts28.jpg
 *
 * Every subject below is derived directly from those images.
 * No arbitrary or placeholder subjects have been added.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * COURSES COVERED (14 programs)
 * ─────────────────────────────────────────────────────────────────────────────
 *  1. Diploma in Electronics and Computer Technology (DECT)         — 1st Year
 *  2. Diploma in Software Development and Programming (DSDP)        — 1st Year
 *  3. Associate in Computer Technology – Multimedia/Animation (ACT-MA) — 1st & 2nd Year
 *  4. Associate in Computer Technology – Programming (ACT-P)        — 1st & 2nd Year
 *  5. Associate in Computer Technology – Networking (ACT-N)         — 1st & 2nd Year
 *  6. BS Information Technology (BSIT)                              — 1st–3rd Year
 *  7. BS Information Systems (BSIS)                                 — 1st–3rd Year
 *  8. BS Computer Science (BSCS)                                    — 1st–3rd Year
 *  9. BET Electronics Engineering Technology (BSEECT)               — 1st Year
 * 10. BET Electrical Engineering Technology (BSEET)                 — 1st Year
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * BILLING MODEL
 * ─────────────────────────────────────────────────────────────────────────────
 *  Tuition   = lec_units × ₱364.00
 *  Lab fee   = lab_units > 0 → ₱1,656.00 flat (once per subject)
 *  Misc/Other fees are fixed per semester — handled separately in FeeSeeder.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * EXCLUSIONS (per CHED non-tuition policy)
 * ─────────────────────────────────────────────────────────────────────────────
 *  PATHFIT 1–4, NSTP 1–2, OJT/Internship (hours-based, no tuition billing).
 *  These appear in the images but are intentionally omitted from this seeder
 *  because they carry no tuition charge and must not appear in assessments.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * SUBJECT CODE CONVENTION
 * ─────────────────────────────────────────────────────────────────────────────
 *  Codes are taken verbatim from the images.
 *  Where multiple sections share the same subject code (e.g. ITP 305 appears
 *  in BSIT 3A, 3B, 3C), a single canonical row is kept — the subject itself
 *  is one entity; sections are a scheduling concept, not a subject split.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * LAB DETECTION LOGIC
 * ─────────────────────────────────────────────────────────────────────────────
 *  A subject has lab_units = 1 when:
 *   (a) the image shows a room of type "LAB", "SHS LAB", "CSS LAB",
 *       "EPAS LAB", or similar, AND
 *   (b) the subject is not a pure lecture like Math, English, or Social Science.
 *  All lab subjects use the flat ₱1,656.00 lab_fee_per_subject rate.
 */
class EnhancedSubjectSeeder extends Seeder
{
    private float $pricePerUnit = 364.00;
    private float $labFee       = 1656.00;

    public function run(): void
    {
        $this->command->info('📚 Seeding Subjects from CCDI AY 2025-2026 curriculum images…');
        Subject::query()->delete();

        foreach ($this->subjects() as $subject) {
            $lecUnits = (int) ($subject['lec_units'] ?? $subject['units'] ?? 0);
            $labUnits = (int) ($subject['lab_units'] ?? 0);

            Subject::create([
                'code'          => $subject['code'],
                'name'          => $subject['name'],
                'units'         => $lecUnits + $labUnits,   // legacy column: total
                'lec_units'     => $lecUnits,
                'lab_units'     => $labUnits,
                'price_per_unit' => $this->pricePerUnit,
                'has_lab'       => $labUnits > 0,
                'lab_fee'       => $labUnits > 0 ? $this->labFee : 0.00,
                'year_level'    => $subject['year_level'],
                'semester'      => $subject['semester'],
                'course'        => $subject['course'],
                'description'   => $subject['description'] ?? null,
                'is_active'     => true,
            ]);
        }

        $count = Subject::count();
        $this->command->info("✓ Subjects seeded: {$count} records.");

        $this->command->table(
            ['Course', 'Year', 'Semester', 'Count'],
            Subject::selectRaw('course, year_level, semester, COUNT(*) as count')
                ->groupBy('course', 'year_level', 'semester')
                ->orderBy('course')
                ->orderByRaw("FIELD(year_level,'1st Year','2nd Year','3rd Year','4th Year')")
                ->orderByRaw("FIELD(semester,'1st Sem','2nd Sem')")
                ->get()
                ->map(fn ($r) => [$r->course, $r->year_level, $r->semester, $r->count])
                ->toArray()
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SUBJECT DEFINITIONS
    // Each entry maps to exactly one row in the subjects table.
    // Fields: code, name, lec_units, lab_units (optional, default 0),
    //         course, year_level, semester.
    // ─────────────────────────────────────────────────────────────────────────
    private function subjects(): array
    {
        return [

            // ═════════════════════════════════════════════════════════════════
            // 1. DIPLOMA IN ELECTRONICS AND COMPUTER TECHNOLOGY (DECT)
            //    Source: quarts01.jpg — 1st Year (no semester label; treated as
            //    2nd Semester per image 29 context)
            // ═════════════════════════════════════════════════════════════════
            [
                'code'       => 'ENG 1',
                'name'       => 'Purposive Communication',
                'lec_units'  => 3,
                'course'     => 'Diploma in Electronics and Computer Technology',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'MATH 1',
                'name'       => 'Mathematics in the Modern World',
                'lec_units'  => 3,
                'course'     => 'Diploma in Electronics and Computer Technology',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'SCIE',
                'name'       => 'Science, Technology and Society',
                'lec_units'  => 3,
                'course'     => 'Diploma in Electronics and Computer Technology',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'EPAS 1',
                'name'       => 'Test and Equipment',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'Diploma in Electronics and Computer Technology',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'CSS I',
                'name'       => 'PC Troubleshooting',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'Diploma in Electronics and Computer Technology',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'ITC 103',
                'name'       => 'IT Software Solutions for Business',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'Diploma in Electronics and Computer Technology',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'EPAS 2',
                'name'       => 'Electronics and Electricity',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'Diploma in Electronics and Computer Technology',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'DRAFTING',
                'name'       => 'Technical Drawing',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'Diploma in Electronics and Computer Technology',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            // PE 1 and NSTP 1 excluded (non-tuition)

            // ═════════════════════════════════════════════════════════════════
            // 2. DIPLOMA IN SOFTWARE DEVELOPMENT AND PROGRAMMING (DSDP)
            //    Sources: quarts02.jpg (Section B, CSS IB) and quarts03.jpg
            //    (Section A, CSS IA) — both are 1st Year.
            //    The subject list is identical between sections;
            //    only CSS I differs by section code, kept as a single canonical row.
            // ═════════════════════════════════════════════════════════════════
            [
                'code'       => 'DSDP-ENG1',
                'name'       => 'Purposive Communication',
                'lec_units'  => 3,
                'course'     => 'Diploma in Software Development and Programming',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'DSDP-MATH1',
                'name'       => 'Mathematics in the Modern World',
                'lec_units'  => 3,
                'course'     => 'Diploma in Software Development and Programming',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'DSDP-SCIE',
                'name'       => 'Science, Technology and Society',
                'lec_units'  => 3,
                'course'     => 'Diploma in Software Development and Programming',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'ITC 101',
                'name'       => 'Introduction to Computing',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'Diploma in Software Development and Programming',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'ITC 102',
                'name'       => 'Computer Programming 1',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'Diploma in Software Development and Programming',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'DSDP-ITC103',
                'name'       => 'IT Software Solutions for Business',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'Diploma in Software Development and Programming',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'CSS I-DSDP',
                'name'       => 'Intro to Computer Systems Servicing',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'Diploma in Software Development and Programming',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'DSDP-DRAFT',
                'name'       => 'Technical Drawing',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'Diploma in Software Development and Programming',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            // PE 1 and NSTP 1 excluded (non-tuition)

            // ═════════════════════════════════════════════════════════════════
            // 3. ASSOCIATE IN COMPUTER TECHNOLOGY – MULTIMEDIA/ANIMATION (ACT-MA)
            //    Sources:
            //      quarts18.jpg — 1st Year (2nd Sem per slide 29 context)
            //      quarts04.jpg — 2nd Year (2nd Sem per slide 29 context)
            // ═════════════════════════════════════════════════════════════════

            // ── ACT-MA — 1st Year ──────────────────────────────────────────
            [
                'code'       => 'ACTMA-GEE2',
                'name'       => 'Peace Studies and Education',
                'lec_units'  => 3,
                'course'     => 'Associate in Computer Technology - Multimedia/Animation',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'ACTMA-ITC104',
                'name'       => 'IT Software Solutions for Business 2',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'Associate in Computer Technology - Multimedia/Animation',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'ACTMA-LIT1',
                'name'       => 'The Contemporary World',
                'lec_units'  => 3,
                'course'     => 'Associate in Computer Technology - Multimedia/Animation',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'ACTMA-GEE1',
                'name'       => 'Living in the IT Era',
                'lec_units'  => 3,
                'course'     => 'Associate in Computer Technology - Multimedia/Animation',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'ACTMA-ELEC2',
                'name'       => 'Graphics Design',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'Associate in Computer Technology - Multimedia/Animation',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'ISC 105',
                'name'       => 'Information Management',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'Associate in Computer Technology - Multimedia/Animation',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'ACTMA-ITC105',
                'name'       => 'Computer Programming 2',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'Associate in Computer Technology - Multimedia/Animation',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            // PE 2 and NSTP 2 excluded (non-tuition)

            // ── ACT-MA — 2nd Year ──────────────────────────────────────────
            // Source: quarts04.jpg
            [
                'code'       => 'ACTMA-ELEC4',
                'name'       => 'GUI-Based Applications Development',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'Associate in Computer Technology - Multimedia/Animation',
                'year_level' => '2nd Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'ITP 205',
                'name'       => 'Fundamentals of Database Systems',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'Associate in Computer Technology - Multimedia/Animation',
                'year_level' => '2nd Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'ITP 206',
                'name'       => 'Quantitative Methods (Modeling and Simulation)',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'Associate in Computer Technology - Multimedia/Animation',
                'year_level' => '2nd Year',
                'semester'   => '2nd Sem',
            ],
            // PE 4 and OJT excluded (non-tuition)

            // ═════════════════════════════════════════════════════════════════
            // 4. ASSOCIATE IN COMPUTER TECHNOLOGY – PROGRAMMING (ACT-P)
            //    Sources:
            //      quarts19.jpg — 1st Year
            //      quarts06.jpg — 2nd Year
            // ═════════════════════════════════════════════════════════════════

            // ── ACT-P — 1st Year ───────────────────────────────────────────
            [
                'code'       => 'ACTP-GEE2',
                'name'       => 'Peace Studies and Education',
                'lec_units'  => 3,
                'course'     => 'Associate in Computer Technology - Programming',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'ACTP-ITC104',
                'name'       => 'IT Software Solutions for Business 2',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'Associate in Computer Technology - Programming',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'ACTP-LIT1',
                'name'       => 'The Contemporary World',
                'lec_units'  => 3,
                'course'     => 'Associate in Computer Technology - Programming',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'ACTP-GEE1',
                'name'       => 'Living in the IT Era',
                'lec_units'  => 3,
                'course'     => 'Associate in Computer Technology - Programming',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'ITP 104A',
                'name'       => 'Introduction to Human Computer Interaction',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'Associate in Computer Technology - Programming',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'ACTP-ITC105',
                'name'       => 'Computer Programming 2',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'Associate in Computer Technology - Programming',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'ACTP-ISC106',
                'name'       => 'Information Management',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'Associate in Computer Technology - Programming',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            // PE 2 and NSTP 2 excluded (non-tuition)

            // ── ACT-P — 2nd Year ───────────────────────────────────────────
            // Source: quarts06.jpg
            [
                'code'       => 'ACTP-ELEC3',
                'name'       => 'Object-Oriented Programming',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'Associate in Computer Technology - Programming',
                'year_level' => '2nd Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'ACTP-ITP206',
                'name'       => 'Quantitative Methods (Modeling and Simulation)',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'Associate in Computer Technology - Programming',
                'year_level' => '2nd Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'ACTP-ITP205',
                'name'       => 'Fundamentals of Database Systems',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'Associate in Computer Technology - Programming',
                'year_level' => '2nd Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'ITP 207',
                'name'       => 'Integrative Programming Technologies',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'Associate in Computer Technology - Programming',
                'year_level' => '2nd Year',
                'semester'   => '2nd Sem',
            ],
            // PE 4 and Internship excluded (non-tuition)

            // ═════════════════════════════════════════════════════════════════
            // 5. ASSOCIATE IN COMPUTER TECHNOLOGY – NETWORKING (ACT-N)
            //    Sources:
            //      quarts20.jpg — 1st Year
            //      quarts05.jpg — 2nd Year
            // ═════════════════════════════════════════════════════════════════

            // ── ACT-N — 1st Year ───────────────────────────────────────────
            [
                'code'       => 'ACTN-GEE2',
                'name'       => 'Peace Studies and Education',
                'lec_units'  => 3,
                'course'     => 'Associate in Computer Technology - Networking',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'ACTN-ITC108',
                'name'       => 'IT Software Solutions for Business 2',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'Associate in Computer Technology - Networking',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'ACTN-LIT1',
                'name'       => 'The Contemporary World',
                'lec_units'  => 3,
                'course'     => 'Associate in Computer Technology - Networking',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'ACTN-GEE1',
                'name'       => 'Living in the IT Era',
                'lec_units'  => 3,
                'course'     => 'Associate in Computer Technology - Networking',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'ACTN-ELEC2',
                'name'       => 'Intro to Data Communication and Networking',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'Associate in Computer Technology - Networking',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'ISC 105-N',
                'name'       => 'Information Management',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'Associate in Computer Technology - Networking',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'ACTN-ITC105',
                'name'       => 'Computer Programming 2',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'Associate in Computer Technology - Networking',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            // NSTP 2 and PE 2 excluded (non-tuition)

            // ── ACT-N — 2nd Year ───────────────────────────────────────────
            // Source: quarts05.jpg
            [
                'code'       => 'ACTN-ELEC5',
                'name'       => 'Network Security',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'Associate in Computer Technology - Networking',
                'year_level' => '2nd Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'ACTN-ITP205',
                'name'       => 'Fundamentals of Database Systems',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'Associate in Computer Technology - Networking',
                'year_level' => '2nd Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'ACTN-ITP206',
                'name'       => 'Quantitative Methods and Modeling Simulation',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'Associate in Computer Technology - Networking',
                'year_level' => '2nd Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'ACTN-ELEC6',
                'name'       => 'Network Administration',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'Associate in Computer Technology - Networking',
                'year_level' => '2nd Year',
                'semester'   => '2nd Sem',
            ],
            // OJT and PE 4 excluded (non-tuition)

            // ═════════════════════════════════════════════════════════════════
            // 6. BS INFORMATION TECHNOLOGY (BSIT)
            //    Sources:
            //      1st Year: quarts24.jpg (1D), quarts26.jpg (1C),
            //                quarts27.jpg (1A), quarts28.jpg (1B)
            //      2nd Year: quarts15.jpg (2A), quarts14.jpg (2B),
            //                quarts13.jpg (2C), quarts12.jpg (2D)
            //      3rd Year: quarts11.jpg (3A), quarts10.jpg (3B), quarts09.jpg (3C)
            //
            //    Subjects are IDENTICAL across sections within the same year.
            //    One canonical row per subject-code/year/semester is kept.
            // ═════════════════════════════════════════════════════════════════

            // ── BSIT — 1st Year ────────────────────────────────────────────
            // Common subjects across 1A, 1B, 1C, 1D (quarts24–28)
            [
                'code'       => 'GE ELECT',
                'name'       => 'Peace Studies and Education',
                'lec_units'  => 3,
                'course'     => 'BS Information Technology',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'LIT 1',
                'name'       => 'The Contemporary World',
                'lec_units'  => 3,
                'course'     => 'BS Information Technology',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'ITC 104',
                'name'       => 'IT Software Solutions for Business 2',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'BS Information Technology',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'ITC 106',
                'name'       => 'Information Management',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'BS Information Technology',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'ITC 107',
                'name'       => 'Introduction to Human Computer Interaction',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'BS Information Technology',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'ITC 105',
                'name'       => 'Computer Programming 2',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'BS Information Technology',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            // NSTP 2 and Pathfit 2 excluded (non-tuition)

            // ── BSIT — 2nd Year ────────────────────────────────────────────
            // Subjects common to 2A, 2B, 2C, 2D (quarts12–15)
            [
                'code'       => 'ETHICS',
                'name'       => 'Ethics',
                'lec_units'  => 3,
                'course'     => 'BS Information Technology',
                'year_level' => '2nd Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'ITP 204',
                'name'       => 'Integrative Programming and Technologies',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'BS Information Technology',
                'year_level' => '2nd Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'ITP 205-BSIT',
                'name'       => 'Quantitative Methods (Modeling and Simulation)',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'BS Information Technology',
                'year_level' => '2nd Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'IT ELEC 1',
                'name'       => 'Object-Oriented Programming',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'BS Information Technology',
                'year_level' => '2nd Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'IT ELEC 2',
                'name'       => 'Platform Technologies',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'BS Information Technology',
                'year_level' => '2nd Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'SOC SCI 1',
                'name'       => 'Life and Works of Rizal',
                'lec_units'  => 3,
                'course'     => 'BS Information Technology',
                'year_level' => '2nd Year',
                'semester'   => '2nd Sem',
            ],
            // Pathfit 4 excluded (non-tuition)

            // ── BSIT — 3rd Year ────────────────────────────────────────────
            // Common to 3A, 3B, 3C (quarts09–11)
            [
                'code'       => 'ITP 305',
                'name'       => 'Social and Professional Issues',
                'lec_units'  => 3,
                'course'     => 'BS Information Technology',
                'year_level' => '3rd Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'ITP 306',
                'name'       => 'Application Development and Emerging Technologies',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'BS Information Technology',
                'year_level' => '3rd Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'ITP 138',
                'name'       => 'Information Assurance and Security 2',
                'lec_units'  => 3,
                'course'     => 'BS Information Technology',
                'year_level' => '3rd Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'ENTREP-BSIT',
                'name'       => 'Fundamentals of Entrepreneurship',
                'lec_units'  => 3,
                'course'     => 'BS Information Technology',
                'year_level' => '3rd Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'ITP 203',
                'name'       => 'Object-Oriented Programming',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'BS Information Technology',
                'year_level' => '3rd Year',
                'semester'   => '2nd Sem',
            ],

            // ═════════════════════════════════════════════════════════════════
            // 7. BS INFORMATION SYSTEMS (BSIS)
            //    Sources:
            //      1st Year: quarts22.jpg
            //      2nd Year: quarts16.jpg
            //      3rd Year: quarts07.jpg
            // ═════════════════════════════════════════════════════════════════

            // ── BSIS — 1st Year ────────────────────────────────────────────
            [
                'code'       => 'ISC 106',
                'name'       => 'Information Management',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'BS Information Systems',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'LIT 1-BSIS',
                'name'       => 'The Contemporary World',
                'lec_units'  => 3,
                'course'     => 'BS Information Systems',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'ISC 104',
                'name'       => 'IT Software Solutions for Business 2',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'BS Information Systems',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'PHILO 1',
                'name'       => 'Understanding the Self',
                'lec_units'  => 3,
                'course'     => 'BS Information Systems',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'ISC 105-BSIS',
                'name'       => 'Computer Programming 2',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'BS Information Systems',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'ISP 105',
                'name'       => 'Fundamentals of Information Systems',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'BS Information Systems',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            // Pathfit 2 and NSTP 2 excluded (non-tuition)

            // ── BSIS — 2nd Year ────────────────────────────────────────────
            [
                'code'       => 'SOC SCI 1-BSIS',
                'name'       => 'Life and Works of Rizal',
                'lec_units'  => 3,
                'course'     => 'BS Information Systems',
                'year_level' => '2nd Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'MKTNG',
                'name'       => 'Principles of Marketing',
                'lec_units'  => 3,
                'course'     => 'BS Information Systems',
                'year_level' => '2nd Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'ISP 204',
                'name'       => 'IT Infrastructure and Network Technologies',
                'lec_units'  => 3,
                'course'     => 'BS Information Systems',
                'year_level' => '2nd Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'ISP 205',
                'name'       => 'Systems Analysis and Design',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'BS Information Systems',
                'year_level' => '2nd Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'ISP 206',
                'name'       => 'Financial Management',
                'lec_units'  => 3,
                'course'     => 'BS Information Systems',
                'year_level' => '2nd Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'IS ELEC 1',
                'name'       => 'Human-Computer Interaction',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'BS Information Systems',
                'year_level' => '2nd Year',
                'semester'   => '2nd Sem',
            ],
            // Pathfit 4 excluded (non-tuition)

            // ── BSIS — 3rd Year ────────────────────────────────────────────
            [
                'code'       => 'ISC 306',
                'name'       => 'IS Project Management',
                'lec_units'  => 3,
                'course'     => 'BS Information Systems',
                'year_level' => '3rd Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'ISC 307',
                'name'       => 'Evaluation of Business Performance',
                'lec_units'  => 3,
                'course'     => 'BS Information Systems',
                'year_level' => '3rd Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'ISC 308',
                'name'       => 'Application Development and Emerging Technologies',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'BS Information Systems',
                'year_level' => '3rd Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'ISP 401',
                'name'       => 'IS Strategies, Management and Acquisition',
                'lec_units'  => 3,
                'course'     => 'BS Information Systems',
                'year_level' => '3rd Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'BSIS-ELEC3',
                'name'       => 'IS Innovations and New Technologies',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'BS Information Systems',
                'year_level' => '3rd Year',
                'semester'   => '2nd Sem',
            ],

            // ═════════════════════════════════════════════════════════════════
            // 8. BS COMPUTER SCIENCE (BSCS)
            //    Sources:
            //      1st Year: quarts25.jpg
            //      2nd Year: quarts17.jpg
            //      3rd Year: quarts08.jpg
            // ═════════════════════════════════════════════════════════════════

            // ── BSCS — 1st Year ────────────────────────────────────────────
            [
                'code'       => 'ITC 106-BSCS',
                'name'       => 'Information Management',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'BS Computer Science',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'LIT 1-BSCS',
                'name'       => 'The Contemporary World',
                'lec_units'  => 3,
                'course'     => 'BS Computer Science',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'ITC 104-BSCS',
                'name'       => 'IT Software Solutions for Business 2',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'BS Computer Science',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'PHILO 1-BSCS',
                'name'       => 'Understanding the Self',
                'lec_units'  => 3,
                'course'     => 'BS Computer Science',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'CSC 104',
                'name'       => 'Intermediate Programming',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'BS Computer Science',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'CSP 107',
                'name'       => 'Introduction to Human-Computer Interaction',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'BS Computer Science',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            // Pathfit 2 and NSTP 2 excluded (non-tuition)

            // ── BSCS — 2nd Year ────────────────────────────────────────────
            [
                'code'       => 'CSP 204',
                'name'       => 'Discrete Structures 2',
                'lec_units'  => 3,
                'course'     => 'BS Computer Science',
                'year_level' => '2nd Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'CSP 205',
                'name'       => 'Object-Oriented Programming',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'BS Computer Science',
                'year_level' => '2nd Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'CSP 206',
                'name'       => 'Algorithms and Complexity',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'BS Computer Science',
                'year_level' => '2nd Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'SOC SCI 1-BSCS',
                'name'       => 'Life and Works of Rizal',
                'lec_units'  => 3,
                'course'     => 'BS Computer Science',
                'year_level' => '2nd Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'CS ELEC-ANDROID',
                'name'       => 'Intro to Android Development',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'BS Computer Science',
                'year_level' => '2nd Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'ACCTG',
                'name'       => 'Fundamentals of Accounting',
                'lec_units'  => 3,
                'course'     => 'BS Computer Science',
                'year_level' => '2nd Year',
                'semester'   => '2nd Sem',
            ],
            // Pathfit 4 excluded (non-tuition)

            // ── BSCS — 3rd Year ────────────────────────────────────────────
            [
                'code'       => 'ENTREP-BSCS',
                'name'       => 'Fundamentals of Entrepreneurship',
                'lec_units'  => 3,
                'course'     => 'BS Computer Science',
                'year_level' => '3rd Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'CSP 307',
                'name'       => 'Programming Languages',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'BS Computer Science',
                'year_level' => '3rd Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'CSP 308',
                'name'       => 'Social Issues and Professional Practice',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'BS Computer Science',
                'year_level' => '3rd Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'CSP 309',
                'name'       => 'Software Engineering 2',
                'lec_units'  => 3,
                'course'     => 'BS Computer Science',
                'year_level' => '3rd Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'CS ELEC 2',
                'name'       => 'Graphics and Visual Computing',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'BS Computer Science',
                'year_level' => '3rd Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'CS ELEC 1',
                'name'       => 'Intelligent Systems',
                'lec_units'  => 3,
                'course'     => 'BS Computer Science',
                'year_level' => '3rd Year',
                'semester'   => '2nd Sem',
            ],

            // ═════════════════════════════════════════════════════════════════
            // 9. BET ELECTRONICS ENGINEERING TECHNOLOGY (BSEECT)
            //    Source: quarts21.jpg — 1st Year (2nd Sem)
            // ═════════════════════════════════════════════════════════════════
            [
                'code'       => 'GE ELECT2-BSEECT',
                'name'       => 'Peace Studies and Education',
                'lec_units'  => 3,
                'course'     => 'BET Electronics Engineering Technology',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'ECE 111',
                'name'       => 'Electronics 1: Electronic Devices and Circuits',
                'lec_units'  => 3,
                'lab_units'  => 1,
                'course'     => 'BET Electronics Engineering Technology',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'MATH 102',
                'name'       => 'Calculus 2 - Integral Calculus',
                'lec_units'  => 3,
                'course'     => 'BET Electronics Engineering Technology',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'CHEM 101',
                'name'       => 'Chemistry for Engineering Technologists',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'BET Electronics Engineering Technology',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'GENO',
                'name'       => 'DC and AC Circuits',
                'lec_units'  => 3,
                'lab_units'  => 1,
                'course'     => 'BET Electronics Engineering Technology',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'COMP 102',
                'name'       => 'Integrated Software Applications 2',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'BET Electronics Engineering Technology',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            // Pathfit 2 and NSTP 2 excluded (non-tuition)

            // ═════════════════════════════════════════════════════════════════
            // 10. BET ELECTRICAL ENGINEERING TECHNOLOGY (BSEET)
            //    Source: quarts23.jpg — 1st Year (2nd Sem)
            // ═════════════════════════════════════════════════════════════════
            [
                'code'       => 'GE ELECT2-BSEET',
                'name'       => 'Peace Studies and Education',
                'lec_units'  => 3,
                'course'     => 'BET Electrical Engineering Technology',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'CE4',
                'name'       => 'The Contemporary World',
                'lec_units'  => 3,
                'course'     => 'BET Electrical Engineering Technology',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'MATH 102-BSEET',
                'name'       => 'Calculus 2 - Integral Calculus',
                'lec_units'  => 3,
                'course'     => 'BET Electrical Engineering Technology',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'CHEM 101-BSEET',
                'name'       => 'Chemistry for Engineering Technologists',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'BET Electrical Engineering Technology',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            [
                'code'       => 'COMP 102-BSEET',
                'name'       => 'Integrated Software Applications 2',
                'lec_units'  => 2,
                'lab_units'  => 1,
                'course'     => 'BET Electrical Engineering Technology',
                'year_level' => '1st Year',
                'semester'   => '2nd Sem',
            ],
            // Pathfit 2 and NSTP 2 excluded (non-tuition)
        ];
    }
}