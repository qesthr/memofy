<script setup>
import { computed } from 'vue'
import { FileText, Paperclip, Loader2 } from 'lucide-vue-next'
import { buksuLogoBase64, memofyLogoBase64 } from '@/utils/logo'

const props = defineProps({
  memo: {
    type: Object,
    required: true
  },
  sender: {
      type: Object,
      default: null, // For preview mode where sender might be current user
  },
  isPreview: {
    type: Boolean,
    default: false
  },
  // If true, shows "Download PDF" button inside the template (for preview modal usage, though we said remove it... 
  // actually the user wants download buttons in Sent/Received views, so maybe this component is just the PAPER)
  // Let's keep this component as just the "Paper" to be embedded.
})

// Helper to format date
const formatDate = (date) => {
  if (!date) return new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

// Helper to check if attachment is image
const isImageAttachment = (attachment) => {
  if (!attachment) return false
  const url = attachment.url || ''
  const name = attachment.name || ''
  return url.match(/\.(jpeg|jpg|gif|png)$/i) || name.match(/\.(jpeg|jpg|gif|png)$/i)
}

// Helper to reliably get department name from various formats (string, JSON string, object)
const getDepartmentName = (dept) => {
  if (!dept) return ''
  
  // Handle stringified JSON (common in MongoDB logs/imports)
  if (typeof dept === 'string' && dept.trim().startsWith('{')) {
    try {
      const parsed = JSON.parse(dept)
      return parsed.name || parsed.NAME || dept
    } catch (e) {
      return dept
    }
  }
  
  // Handle direct string
  if (typeof dept === 'string') return dept
  
  // Handle object
  if (typeof dept === 'object') {
    return dept.name || dept.NAME || (dept.id || dept.ID ? (dept.id || dept.ID) : '')
  }
  
  return String(dept)
}

// Logic to split content into pages (simplified for now, relying on CSS print rules or manual page breaks if needed)
// For now, we will treat the content as a continuous block and let the PDF generator/printer handle breaks naturally,
// OR we can implement the same manual paging logic from ComposeMemoModal if we want exact preview control.
// The user praised the "Preview" in ComposeMemoModal, so let's try to adapt that paging logic here.

// But wait, `ComposeMemoModal` computes pages based on *input text*. 
// Existing memos (sent/received) have *formatted text* (maybe HTML?). 
// If it's plain text with newlines, we can split it.
// If the prop `memo.message` (or `content`) is passed, we use that.

const contentPages = computed(() => {
    const text = props.memo.message || props.memo.content || ''
    // Rough estimation: 3000 chars per page? Or split by newlines? 
    // This is tricky to get identical to "Compose" without the exact same resizing logic.
    // For "View" mode, maybe just one long scrollable page is fine, and we rely on html2pdf's automatic page splitting.
    // However, the user specifically asked for "Header on every page".
    // html2pdf can repeat headers if we use `<thead>` in a table structure, or we can just let html2pdf handle it.
    
    // START SIMPLE: 
    // Return an array with 1 item containing all content. 
    // If we need strict per-page headers in the PDF, we might need a specific structure for html2pdf.
    // Actually, the user's "Compose" modal logic manually slices text. 
    // Let's try to respect that if we can, but for read-only memos, the content is already set.
    
    // Update: user said "Header on every page". 
    // Best way with html2pdf for variable content is to use a specific page-break configuration or header overlay. 
    // BUT the simplest "Vue" way is to render specifically sized "Divs" (Pages) and print them.
    // Let's stick to the "Long scroll" for now, but configured with CSS for print?
    // No, user wants a specific "A4" look in the preview too.
    
    // Let's use a simplified splitter for now: every ~3000 characters or 40 lines.
    const pages = []
    const lines = text.split('\n')
    let currentPage = ''
    let lineCount = 0
    const LINES_PER_PAGE = 45 // Approximation
    
    for (const line of lines) {
        if (lineCount + 1 > LINES_PER_PAGE) {
            pages.push({ text: currentPage })
            currentPage = ''
            lineCount = 0
        }
        currentPage += line + '\n'
        lineCount++
    }
    if (currentPage) pages.push({ text: currentPage })
    
    if (pages.length === 0) pages.push({ text: '' })
    return pages
})

</script>

<template>
  <div class="memo-pdf-template bg-gray-100 flex flex-col items-center gap-8 py-8"
     style="width: 100%; overflow-x: hidden;">
    
    <div 
        v-for="(page, index) in contentPages" 
        :key="index"
        class="memo-a4-page bg-white shadow-lg relative print:shadow-none print:m-0"
        :class="{ 'mb-8': index < contentPages.length - 1 }"
    >
        <!-- Page Header with Logo -->
        <div class="memo-header border-b-2 border-black px-12 pt-10 pb-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <!-- Logos -->
                    <img :src="memofyLogoBase64" alt="Memofy Logo" class="h-14 w-auto object-contain" />
                    <img :src="buksuLogoBase64" alt="BukSU Logo" class="h-14 w-auto object-contain" />
                    
                    <div class="border-l-2 border-black pl-6">
                        <h1 class="text-3xl font-black tracking-widest text-black uppercase leading-none mb-1">MEMO</h1>
                        <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-black/60">BukSU Memofy Official</p>
                    </div>
                </div>
                
                <div class="text-right">
                    <p class="text-[11px] font-bold text-black/60 uppercase mb-1">
                        {{ getDepartmentName(memo.department || sender?.department?.name || sender?.department || 'Department') }}
                    </p>
                    <p class="text-[12px] font-black text-black">
                        {{ formatDate(memo.created_at || new Date()) }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Page Content Area -->
        <div class="memo-content px-12 py-8 min-h-[800px] flex flex-col">
            
            <!-- Metadata (First Page Only) -->
            <div v-if="index === 0" class="memo-metadata space-y-3 mb-8">
                <div class="flex gap-4">
                    <span class="w-20 font-bold text-[11px] uppercase text-black shrink-0 pt-0.5">TO:</span>
                    <div class="font-black underline uppercase text-[13px] text-black flex flex-wrap gap-x-2 leading-tight">
                         <template v-if="memo.recipient">
                            <span>{{ memo.recipient.first_name }} {{ memo.recipient.last_name }}</span>
                         </template>
                         <template v-else-if="memo.recipient_ids?.length > 0 && memo.recipients_list?.length > 0">
                            <span v-for="(r, i) in memo.recipients_list" :key="r.id">
                                {{ r.first_name }} {{ r.last_name }}{{ i < memo.recipients_list.length - 1 ? ',' : '' }}
                            </span>
                         </template>
                         <template v-else-if="memo.selectedRecipients && memo.selectedRecipients.length > 0">
                            <span v-for="(r, i) in memo.selectedRecipients" :key="r.id">
                                {{ r.first_name }} {{ r.last_name }}{{ i < memo.selectedRecipients.length - 1 ? ',' : '' }}
                            </span>
                         </template>
                         <template v-else-if="memo.department">
                             {{ getDepartmentName(memo.department) }}
                         </template>
                         <template v-else>
                             <span class="text-black/40 italic">RECIPIENT</span>
                         </template>
                    </div>
                </div>

                <div class="flex gap-4">
                    <span class="w-20 font-bold text-[11px] uppercase text-black shrink-0 pt-0.5">FROM:</span>
                    <span class="font-black uppercase text-[13px] text-black leading-tight">
                        {{ memo.sender?.first_name || sender?.first_name || 'SENDER' }} 
                        {{ memo.sender?.last_name || sender?.last_name || '' }} 
                        <span class="text-black/60 font-bold mx-1">/</span> 
                        {{ getDepartmentName(memo.department || memo.sender?.department || sender?.department?.name || sender?.department || 'DEPARTMENT') }}
                    </span>
                </div>

                <div class="flex gap-4">
                    <span class="w-20 font-bold text-[11px] uppercase text-black shrink-0 pt-0.5">SUBJECT:</span>
                    <span class="font-black uppercase text-[13px] text-black leading-tight">{{ memo.subject || 'NO SUBJECT' }}</span>
                </div>

                 <div class="flex gap-4">
                    <span class="w-20 font-bold text-[11px] uppercase text-black shrink-0 pt-0.5">DATE:</span>
                    <span class="font-black uppercase text-[13px] text-black leading-tight">{{ formatDate(memo.created_at || new Date()) }}</span>
                </div>
            </div>

            <!-- Separator (First Page Only) -->
            <div v-if="index === 0" class="w-full border-b border-black/20 mb-8"></div>

            <!-- Body Text -->
            <div class="memo-body text-[13px] leading-7 whitespace-pre-wrap font-medium text-justify text-black font-sans">
                {{ page.text }}
            </div>

            <!-- Attachments & Signatures (Last Page Only) -->
            <div v-if="index === contentPages.length - 1" class="mt-auto pt-8">
                
                <!-- Attachments -->
                <div v-if="memo.attachments && memo.attachments.length > 0" class="mb-8 pt-6 border-t border-black/10">
                    <p class="text-[10px] font-bold uppercase text-black/50 mb-4 tracking-wider">Attached Files:</p>
                    
                    <!-- Sequential Images List (Readability prioritied) -->
                    <div class="flex flex-col gap-8 mb-8">
                        <template v-for="att in memo.attachments" :key="att.id || att.name">
                            <div v-if="isImageAttachment(att)" class="w-full">
                                <img :src="att.url" class="max-w-full h-auto object-contain mx-auto border border-gray-100 rounded-sm" />
                            </div>
                        </template>
                    </div>

                    <!-- Files List -->
                     <div class="flex flex-wrap gap-2">
                         <template v-for="att in memo.attachments" :key="att.id || att.name">
                            <a v-if="!isImageAttachment(att)" 
                               :href="att.url" 
                               :download="att.name"
                               target="_blank"
                               class="flex items-center gap-2 bg-gray-50 border border-gray-200 px-3 py-1.5 rounded text-[11px] text-black/70 hover:bg-gray-100 transition-colors"
                            >
                                <Paperclip :size="12" />
                                <span class="truncate max-w-[150px]">{{ att.name }}</span>
                            </a>
                         </template>
                     </div>
                </div>



            </div>

        </div>

         <!-- Page Footer -->
        <div class="memo-footer absolute bottom-0 left-0 right-0 px-12 py-4 border-t border-black/10 flex justify-between items-center bg-white">
            <p class="text-[9px] text-black/40 uppercase tracking-wider">Generated by Memofy System</p>
            <p class="text-[10px] font-bold text-black/60">Page {{ index + 1 }} of {{ contentPages.length }}</p>
        </div>

    </div>
  </div>
</template>

<style scoped>
/* A4 Dimensions */
.memo-a4-page {
    width: min(210mm, 100%);
    min-height: 297mm;
    display: flex;
    flex-direction: column;
    box-sizing: border-box;
}

@media print {
    .memo-pdf-template {
        background: white;
        padding: 0;
    }
    .memo-a4-page {
        box-shadow: none;
        margin: 0;
        page-break-after: always;
    }
    .memo-a4-page:last-child {
        page-break-after: auto;
    }
}
</style>
