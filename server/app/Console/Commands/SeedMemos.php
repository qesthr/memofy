<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Memo;
use App\Models\User;
use App\Models\Department;
use Illuminate\Support\Str;
use Carbon\Carbon;
use MongoDB\BSON\ObjectId;

class SeedMemos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'memos:seed
                            {--count=100 : Number of memos to create}
                            {--truncate : Clear existing memos before seeding}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed random memos with realistic content (For Review, Approved, Disseminated statuses only)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $count = (int) $this->option('count');
        $truncate = $this->option('truncate');
        
        $this->info("Starting Memo Seeding...");
        
        // Get existing users by role
        $admins = User::where('role', 'admin')->get();
        $secretaries = User::where('role', 'secretary')->get();
        $faculties = User::where('role', 'faculty')->get();
        
        $allUsers = User::all();
        
        // Check if we have users
        if ($allUsers->isEmpty()) {
            $this->error('No users found! Please create users first.');
            $this->info('Test users:');
            $this->info('  Admin: Admin1@buksu.edu.ph (Password: Admin123!)');
            $this->info('  Secretary: Secretary1@buksu.edu.ph (Password: Secretary123!)');
            $this->info('  Faculty: Faculty1@buksu.edu.ph (Password: Faculty123!)');
            return Command::FAILURE;
        }
        
        // Truncate if requested
        if ($truncate) {
            $this->warn('Truncating existing memos...');
            Memo::truncate();
            $this->info('Existing memos cleared.');
        }
        
        // Get departments
        $departments = Department::all();
        
        $this->info("Found {$allUsers->count()} users (Admins: {$admins->count()}, Secretaries: {$secretaries->count()}, Faculty: {$faculties->count()})");
        $this->info("Creating {$count} memos...");
        
        // Prepare user IDs
        $adminIds = $admins->pluck('_id')->map(fn($id) => (string)$id)->toArray();
        $secretaryIds = $secretaries->pluck('_id')->map(fn($id) => (string)$id)->toArray();
        $facultyIds = $faculties->pluck('_id')->map(fn($id) => (string)$id)->toArray();
        $allUserIds = $allUsers->pluck('_id')->map(fn($id) => (string)$id)->toArray();
        
        // Combine admins and secretaries as potential authors
        $authorIds = array_merge($adminIds, $secretaryIds);
        if (empty($authorIds)) {
            $authorIds = $allUserIds; // Fallback to all users
        }
        
        // Progress bar
        $bar = $this->output->createProgressBar($count);
        
        $memosCreated = 0;
        $batchData = [];
        $batchSize = 50;
        
        for ($i = 0; $i < $count; $i++) {
            $memoData = $this->generateRandomMemo(
                $authorIds,
                $adminIds,
                $allUserIds,
                $facultyIds,
                $departments->pluck('_id')->map(fn($id) => (string)$id)->toArray(),
                $i
            );
            
            $batchData[] = $memoData;
            $memosCreated++;
            
            // Insert in batches for performance
            if (count($batchData) >= $batchSize) {
                Memo::insert($batchData);
                $batchData = [];
            }
            
            $bar->advance();
        }
        
        // Insert remaining memos
        if (!empty($batchData)) {
            Memo::insert($batchData);
        }
        
        $bar->finish();
        $this->newLine(2);
        $this->info("Memo Seeding Complete! Created {$memosCreated} memos.");
        
        // Summary
        $this->displaySummary();
        
        return Command::SUCCESS;
    }
    
    /**
     * Generate a random memo with realistic content.
     */
    private function generateRandomMemo(
        array $authorIds,
        array $adminIds,
        array $allUserIds,
        array $facultyIds,
        array $departmentIds,
        int $index
    ): array {
        // Determine status distribution:
        // 20% pending_approval (For Review)
        // 60% sent (Approved/Disseminated)
        // 20% read (Acknowledged)
        $statusRandom = rand(1, 100);
        if ($statusRandom <= 20) {
            $status = 'pending_approval';
        } elseif ($statusRandom <= 80) {
            $status = 'sent';
        } else {
            $status = 'read';
        }
        
        // Get random author
        $authorId = $this->getRandomElement($authorIds);
        
        // Get random recipients (1-5 recipients)
        $recipientCount = rand(1, min(5, count($allUserIds)));
        $recipientIds = $this->getRandomElements($allUserIds, $recipientCount);
        
        // Generate memo content
        $subjectData = $this->getRandomSubject();
        $subject = $subjectData['subject'];
        $message = $this->generateMessage($subjectData['type']);
        
        // Generate priority (weighted)
        $priorityRandom = rand(1, 100);
        if ($priorityRandom <= 20) {
            $priority = 'high';
        } elseif ($priorityRandom <= 70) {
            $priority = 'medium';
        } else {
            $priority = 'low';
        }
        
        // Generate dates
        $createdAt = Carbon::now()->subDays(rand(1, 90))->subHours(rand(0, 23))->subMinutes(rand(0, 59));
        $updatedAt = $createdAt->copy()->addHours(rand(0, 48));
        
        $memoData = [
            '_id' => new ObjectId(),
            'created_by' => $authorId,
            'sender_id' => $authorId,
            'recipient_ids' => $recipientIds,
            'subject' => $subject,
            'message' => $message,
            'priority' => $priority,
            'status' => $status,
            'version' => 1,
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ];
        
        // Add department if available
        if (!empty($departmentIds) && rand(1, 100) <= 70) {
            $memoData['department_id'] = $this->getRandomElement($departmentIds);
        }
        
        // Add approval data for sent/read memos
        if (($status === 'sent' || $status === 'read') && !empty($adminIds)) {
            $memoData['approved_by'] = $this->getRandomElement($adminIds);
            $memoData['approved_at'] = $createdAt->copy()->addHours(rand(1, 24));
        }
        
        // Add scheduled date for some memos
        if (rand(1, 100) <= 15) {
            $memoData['scheduled_send_at'] = Carbon::now()->addDays(rand(1, 30));
        }
        
        // Add attachments for some memos
        if (rand(1, 100) <= 25) {
            $memoData['attachments'] = $this->generateAttachments();
        }
        
        return $memoData;
    }
    
    /**
     * Get random subject with type.
     */
    private function getRandomSubject(): array
    {
        $subjects = [
            ['subject' => 'Faculty Meeting Schedule', 'type' => 'meeting'],
            ['subject' => 'Grade Submission Deadline', 'type' => 'deadline'],
            ['subject' => 'Department Event Announcement', 'type' => 'event'],
            ['subject' => 'Office Hours Update', 'type' => 'announcement'],
            ['subject' => 'Research Grant Application', 'type' => 'research'],
            ['subject' => 'Workshop Attendance Request', 'type' => 'training'],
            ['subject' => 'Classroom Assignment Notice', 'type' => 'assignment'],
            ['subject' => 'University Policy Update', 'type' => 'policy'],
            ['subject' => 'Semester Schedule Release', 'type' => 'schedule'],
            ['subject' => 'Faculty Evaluation Schedule', 'type' => 'evaluation'],
            ['subject' => 'Budget Allocation Notice', 'type' => 'budget'],
            ['subject' => 'Holiday Schedule Announcement', 'type' => 'holiday'],
            ['subject' => 'Equipment Request Approval', 'type' => 'equipment'],
            ['subject' => 'Student Affairs Update', 'type' => 'student'],
            ['subject' => 'Library Hours Extension', 'type' => 'facility'],
            ['subject' => 'IT System Maintenance Notice', 'type' => 'maintenance'],
            ['subject' => 'Professional Development Opportunity', 'type' => 'training'],
            ['subject' => 'Curriculum Revision Notice', 'type' => 'academic'],
            ['subject' => 'Examination Schedule Release', 'type' => 'exam'],
            ['subject' => 'Enrollment Period Reminder', 'type' => 'enrollment'],
            ['subject' => 'Faculty Load Assignment', 'type' => 'assignment'],
            ['subject' => 'Research Paper Submission', 'type' => 'research'],
            ['subject' => 'Conference Participation Request', 'type' => 'training'],
            ['subject' => 'Laboratory Safety Guidelines', 'type' => 'safety'],
            ['subject' => 'Student Advising Schedule', 'type' => 'student'],
            ['subject' => 'Department Meeting Minutes', 'type' => 'meeting'],
            ['subject' => 'Promotion Application Notice', 'type' => 'hr'],
            ['subject' => 'Leave Application Reminder', 'type' => 'hr'],
            ['subject' => 'Annual Report Submission', 'type' => 'report'],
            ['subject' => 'Accreditation Preparation', 'type' => 'academic'],
        ];
        
        return $this->getRandomElement($subjects);
    }
    
    /**
     * Generate realistic message based on type.
     */
    private function generateMessage(string $type): string
    {
        $greetings = [
            'Dear Faculty Members,',
            'Dear Colleagues,',
            'To All Concerned,',
            'Dear Staff,',
            'Good day,',
        ];
        
        $closings = [
            'Thank you for your cooperation.',
            'Your prompt attention to this matter is appreciated.',
            'Please acknowledge receipt of this memo.',
            'For any questions, please contact the department office.',
            'We appreciate your continued support.',
        ];
        
        $contentByType = [
            'meeting' => [
                'Please be informed that a department meeting is scheduled.',
                'Attendance is required for all faculty members.',
                'Agenda will be provided prior to the meeting.',
            ],
            'deadline' => [
                'This serves as a reminder for the upcoming deadline.',
                'Please ensure all requirements are submitted on time.',
                'Late submissions may not be accommodated.',
            ],
            'event' => [
                'We are pleased to announce an upcoming department event.',
                'All faculty members are encouraged to participate.',
                'Further details will be provided soon.',
            ],
            'announcement' => [
                'Please take note of the following announcement.',
                'This information is effective immediately unless otherwise stated.',
                'Please disseminate to concerned parties.',
            ],
            'research' => [
                'This memo pertains to research-related matters.',
                'Interested parties may submit their applications.',
                'For inquiries, please contact the research office.',
            ],
            'training' => [
                'A professional development opportunity is available.',
                'Interested participants should submit their applications.',
                'Limited slots are available.',
            ],
            'assignment' => [
                'Please take note of your assigned responsibilities.',
                'Any concerns should be raised immediately.',
                'This assignment is effective for the current semester.',
            ],
            'policy' => [
                'Please be advised of the updated university policies.',
                'Compliance is expected from all concerned.',
                'Questions may be directed to the appropriate office.',
            ],
            'schedule' => [
                'The schedule has been finalized and is now available.',
                'Please review your assigned slots.',
                'Request for changes should be submitted within the week.',
            ],
            'evaluation' => [
                'The evaluation period is approaching.',
                'Please prepare all necessary documents.',
                'The schedule will be provided separately.',
            ],
            'budget' => [
                'This memo concerns budget allocation for the department.',
                'Please submit your budget proposals accordingly.',
                'All requests are subject to approval.',
            ],
            'holiday' => [
                'Please take note of the upcoming holiday schedule.',
                'Classes and office work will be suspended accordingly.',
                'Regular operations resume on the specified date.',
            ],
            'equipment' => [
                'Equipment requests are now being processed.',
                'Please submit your requirements to the office.',
                'Priority will be given to urgent needs.',
            ],
            'student' => [
                'This memo concerns student-related matters.',
                'Please ensure proper guidance is provided.',
                'Report any concerns to the department head.',
            ],
            'facility' => [
                'Please be informed of facility-related updates.',
                'Schedule changes may apply.',
                'For reservations, contact the facility office.',
            ],
            'maintenance' => [
                'Scheduled maintenance will be conducted.',
                'Please save your work and log off during the specified period.',
                'We apologize for any inconvenience.',
            ],
            'academic' => [
                'This memo pertains to academic matters.',
                'Please review the attached guidelines.',
                'Implementation is effective immediately.',
            ],
            'exam' => [
                'The examination schedule has been released.',
                'Please ensure all examinations are prepared accordingly.',
                'Students should be informed of their schedules.',
            ],
            'enrollment' => [
                'Enrollment period is now open.',
                'Please assist students with their enrollment concerns.',
                'Deadline for enrollment will be strictly followed.',
            ],
            'safety' => [
                'Please observe the following safety guidelines.',
                'Compliance is mandatory for all personnel.',
                'Report any safety concerns immediately.',
            ],
            'hr' => [
                'This memo concerns human resource matters.',
                'Please submit all required documents on time.',
                'For questions, contact the HR office.',
            ],
            'report' => [
                'Please submit your reports as required.',
                'Follow the prescribed format for submissions.',
                'Late reports may affect your evaluation.',
            ],
        ];
        
        $contents = $contentByType[$type] ?? [
            'Please take note of the following information.',
            'This memo is for your reference and appropriate action.',
            'For questions, please contact the concerned office.',
        ];
        
        $message = $this->getRandomElement($greetings) . "\n\n";
        $message .= $contents[0] . "\n\n";
        $message .= $contents[1] . "\n\n";
        $message .= $contents[2] . "\n\n";
        $message .= $this->getRandomElement($closings);
        
        return $message;
    }
    
    /**
     * Generate random attachments.
     */
    private function generateAttachments(): array
    {
        $attachmentTypes = [
            ['name' => 'Memo_Attachment.pdf', 'type' => 'application/pdf'],
            ['name' => 'Schedule.xlsx', 'type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            ['name' => 'Guidelines.docx', 'type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            ['name' => 'Report.pdf', 'type' => 'application/pdf'],
            ['name' => 'Form_Template.pdf', 'type' => 'application/pdf'],
        ];
        
        $attachment = $this->getRandomElement($attachmentTypes);
        
        return [
            [
                'name' => $attachment['name'],
                'path' => 'attachments/' . Str::random(20) . '_' . $attachment['name'],
                'size' => rand(50000, 5000000),
                'type' => $attachment['type'],
            ]
        ];
    }
    
    /**
     * Get random element from array.
     */
    private function getRandomElement(array $array): mixed
    {
        if (empty($array)) {
            return null;
        }
        return $array[array_rand($array)];
    }
    
    /**
     * Get multiple random elements from array.
     */
    private function getRandomElements(array $array, int $count): array
    {
        if (empty($array)) {
            return [];
        }
        
        $count = min($count, count($array));
        $keys = array_rand($array, $count);
        
        if (!is_array($keys)) {
            $keys = [$keys];
        }
        
        return array_map(fn($key) => $array[$key], $keys);
    }
    
    /**
     * Display summary of created memos.
     */
    private function displaySummary(): void
    {
        $this->newLine();
        $this->info('=== Memo Summary ===');
        
        $total = Memo::count();
        $pending = Memo::where('status', 'pending_approval')->count();
        $sent = Memo::where('status', 'sent')->count();
        $read = Memo::where('status', 'read')->count();
        
        $this->table(
            ['Status', 'Count', 'Percentage'],
            [
                ['For Review (pending_approval)', $pending, $total > 0 ? round(($pending / $total) * 100, 1) . '%' : '0%'],
                ['Disseminated (sent)', $sent, $total > 0 ? round(($sent / $total) * 100, 1) . '%' : '0%'],
                ['Acknowledged (read)', $read, $total > 0 ? round(($read / $total) * 100, 1) . '%' : '0%'],
                ['Total', $total, '100%'],
            ]
        );
    }
}
