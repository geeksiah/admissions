<?php
// Application Forms Builder panel - Dynamic form creation and management

$msg=''; $type='';
try { 
  $pdo->exec("CREATE TABLE IF NOT EXISTS form_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    program_id INT UNSIGNED,
    form_structure JSON NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_program(program_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  // Ensure uniqueness by (name, program_id)
  try { $pdo->exec("ALTER TABLE form_templates ADD UNIQUE KEY uniq_name_program (name, program_id)"); } catch (Throwable $e) {
    try {
      // Remove older duplicates, keep highest id per (name, program_id)
      $pdo->exec("DELETE t1 FROM form_templates t1 INNER JOIN form_templates t2 ON t1.name=t2.name AND COALESCE(t1.program_id,0)=COALESCE(t2.program_id,0) AND t1.id < t2.id");
      $pdo->exec("ALTER TABLE form_templates ADD UNIQUE KEY uniq_name_program (name, program_id)");
    } catch (Throwable $e2) { /* ignore */ }
  }
  
  $pdo->exec("CREATE TABLE IF NOT EXISTS form_fields (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_id INT UNSIGNED,
    field_name VARCHAR(100) NOT NULL,
    field_type ENUM('text', 'email', 'phone', 'number', 'date', 'select', 'textarea', 'file', 'checkbox', 'radio', 'section') NOT NULL,
    label VARCHAR(200) NOT NULL,
    placeholder VARCHAR(200),
    required TINYINT(1) DEFAULT 0,
    validation_rules JSON,
    options JSON,
    conditional_logic JSON,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_template(template_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  
  // Insert default form template only once when table is empty
  $defaultTemplate = [
    'name' => 'Default Application Form',
    'description' => 'Standard application form for all programs',
    'program_id' => null,
    'form_structure' => json_encode([
      'sections' => [
        [
          'id' => 'personal_info',
          'title' => 'Personal Information',
          'fields' => [
            ['name' => 'first_name', 'type' => 'text', 'label' => 'First Name', 'required' => true],
            ['name' => 'last_name', 'type' => 'text', 'label' => 'Last Name', 'required' => true],
            ['name' => 'email', 'type' => 'email', 'label' => 'Email Address', 'required' => true],
            ['name' => 'phone', 'type' => 'phone', 'label' => 'Phone Number', 'required' => true],
            ['name' => 'date_of_birth', 'type' => 'date', 'label' => 'Date of Birth', 'required' => true],
            ['name' => 'gender', 'type' => 'select', 'label' => 'Gender', 'required' => true, 'options' => ['Male', 'Female', 'Other']]
          ]
        ],
        [
          'id' => 'academic_info',
          'title' => 'Academic Information',
          'fields' => [
            ['name' => 'previous_school', 'type' => 'text', 'label' => 'Previous School', 'required' => true],
            ['name' => 'qualification', 'type' => 'select', 'label' => 'Highest Qualification', 'required' => true, 'options' => ['WASSCE', 'SSSCE', 'GCE A-Level', 'Diploma', 'Degree']],
            ['name' => 'academic_transcript', 'type' => 'file', 'label' => 'Academic Transcript', 'required' => true]
          ]
        ]
      ]
    ])
  ];
  
  try {
    $cnt = (int)$pdo->query("SELECT COUNT(*) FROM form_templates")->fetchColumn();
    if ($cnt === 0) {
      $stmt = $pdo->prepare("INSERT INTO form_templates (name, description, program_id, form_structure) VALUES (?, ?, ?, ?)");
      $stmt->execute([$defaultTemplate['name'], $defaultTemplate['description'], $defaultTemplate['program_id'], $defaultTemplate['form_structure']]);
    }
  } catch (Throwable $e) { /* ignore */ }
} catch (Throwable $e) { /* ignore */ }

// Handle actions
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $action = $_POST['action'] ?? '';
  try {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) { 
      throw new RuntimeException('Invalid request'); 
    }
    
    if ($action==='save_template') {
      $name = trim($_POST['template_name'] ?? '');
      $description = trim($_POST['template_description'] ?? '');
      $programId = (int)($_POST['program_id'] ?? 0);
      $formStructure = $_POST['form_structure'] ?? '{}';
      
      if (!$name) {
        throw new RuntimeException('Template name is required');
      }
      
      // Validate JSON structure
      $structure = json_decode($formStructure, true);
      if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('Invalid form structure JSON');
      }
      
      $stmt = $pdo->prepare("INSERT INTO form_templates (name, description, program_id, form_structure) VALUES (?, ?, ?, ?)");
      $stmt->execute([$name, $description, $programId ?: null, $formStructure]);
      
      $msg='Form template saved successfully'; $type='success';
      
    } elseif ($action==='update_template') {
      $id = (int)($_POST['template_id'] ?? 0);
      $name = trim($_POST['template_name'] ?? '');
      $description = trim($_POST['template_description'] ?? '');
      $programId = (int)($_POST['program_id'] ?? 0);
      $formStructure = $_POST['form_structure'] ?? '{}';
      
      if (!$id || !$name) {
        throw new RuntimeException('Template ID and name are required');
      }
      
      $structure = json_decode($formStructure, true);
      if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('Invalid form structure JSON');
      }
      
      $stmt = $pdo->prepare("UPDATE form_templates SET name=?, description=?, program_id=?, form_structure=? WHERE id=?");
      $stmt->execute([$name, $description, $programId ?: null, $formStructure, $id]);
      
      $msg='Form template updated successfully'; $type='success';
      
    } elseif ($action==='toggle_template') {
      $id = (int)($_POST['template_id'] ?? 0);
      $isActive = (int)($_POST['is_active'] ?? 0);
      
      if (!$id) throw new RuntimeException('Template ID required');
      
      $stmt = $pdo->prepare("UPDATE form_templates SET is_active=? WHERE id=?");
      $stmt->execute([$isActive, $id]);
      
      $msg='Template status updated'; $type='success';
      
    } elseif ($action==='delete_template') {
      $id = (int)($_POST['template_id'] ?? 0);
      
      if (!$id) throw new RuntimeException('Template ID required');
      
      $stmt = $pdo->prepare("DELETE FROM form_templates WHERE id=?");
      $stmt->execute([$id]);
      
      $msg='Template deleted successfully'; $type='success';
    } elseif ($action==='duplicate_template') {
      $id = (int)($_POST['template_id'] ?? 0);
      if (!$id) throw new RuntimeException('Template ID required');
      $st = $pdo->prepare("SELECT * FROM form_templates WHERE id=?");
      $st->execute([$id]);
      $tpl = $st->fetch(PDO::FETCH_ASSOC);
      if (!$tpl) throw new RuntimeException('Template not found');
      $base = ($tpl['name'] ?? 'Form') . ' (Copy)';
      $name = $base; $i = 2;
      while (true) {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM form_templates WHERE name=? AND COALESCE(program_id,0)=COALESCE(?,0)");
        $chk->execute([$name, $tpl['program_id']]);
        if ((int)$chk->fetchColumn() === 0) break;
        $name = $base . ' ' . $i++;
      }
      $ins = $pdo->prepare("INSERT INTO form_templates (name, description, program_id, form_structure, is_active) VALUES (?,?,?,?,1)");
      $ins->execute([$name, $tpl['description'], $tpl['program_id'], $tpl['form_structure']]);
      $msg='Form duplicated. You can now edit it.'; $type='success';
    }
  } catch (Throwable $e) { 
    $msg='Failed: '.$e->getMessage(); 
    $type='danger'; 
  }
}

// Fetch templates
$templates = [];
try {
  $stmt = $pdo->query("
    SELECT ft.*, p.name as program_name 
    FROM form_templates ft 
    LEFT JOIN programs p ON ft.program_id = p.id 
    ORDER BY ft.created_at DESC
  ");
  $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* ignore */ }

// Fetch programs for dropdown
$programs = [];
try {
  $stmt = $pdo->query("SELECT id, name FROM programs WHERE status = 'active' ORDER BY name");
  $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* ignore */ }

// Field types for form builder
$fieldTypes = [
  'text' => 'Text Input',
  'email' => 'Email',
  'phone' => 'Phone Number',
  'number' => 'Number',
  'date' => 'Date Picker',
  'select' => 'Dropdown Select',
  'textarea' => 'Text Area',
  'file' => 'File Upload',
  'checkbox' => 'Checkbox',
  'radio' => 'Radio Button',
  'section' => 'Section Header'
];
?>

<?php if($msg): ?>
<div class="card" style="border-left:4px solid <?php echo $type==='success'?'#10b981':'#ef4444'; ?>;margin-bottom:12px;"><?php echo htmlspecialchars($msg); ?></div>
<?php endif; ?>

<div class="panel-card">
  <h3>Application Form Templates</h3>
  
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <div class="muted">Create and manage dynamic application forms for different programs</div>
    <button class="btn" onclick="showFormBuilder()">
      <i class="bi bi-plus-lg"></i> Create New Form
    </button>
  </div>

  <?php if(empty($templates)): ?>
    <div class="card" style="text-align:center;padding:40px">
      <div style="font-size:48px;margin-bottom:16px;color:var(--muted)">📋</div>
      <h4>No Form Templates</h4>
      <p class="muted">Create your first application form template to get started.</p>
      <button class="btn" onclick="showFormBuilder()">
        <i class="bi bi-plus-lg"></i> Create First Form
      </button>
    </div>
  <?php else: ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(350px,1fr));gap:16px">
      <?php foreach($templates as $template): ?>
        <div style="border:1px solid var(--border);border-radius:12px;padding:20px;background:var(--card)">
          <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:12px">
            <div>
              <h4 style="margin:0;font-size:16px"><?php echo htmlspecialchars($template['name']); ?></h4>
              <div class="muted" style="font-size:12px;margin-top:4px">
                <?php if($template['program_name']): ?>
                  Program: <?php echo htmlspecialchars($template['program_name']); ?>
                <?php else: ?>
                  General Template
                <?php endif; ?>
              </div>
            </div>
            <div style="display:flex;gap:4px">
              <span style="font-size:11px;padding:2px 6px;border-radius:4px;background:<?php echo $template['is_active'] ? '#10b981' : '#6b7280'; ?>;color:white">
                <?php echo $template['is_active'] ? 'Active' : 'Inactive'; ?>
              </span>
            </div>
          </div>
          
          <?php if($template['description']): ?>
            <p class="muted" style="font-size:14px;margin-bottom:12px"><?php echo htmlspecialchars($template['description']); ?></p>
          <?php endif; ?>
          
          <div style="display:flex;gap:8px;margin-top:16px;flex-wrap:wrap">
            <button class="btn secondary" onclick="editTemplate(<?php echo htmlspecialchars(json_encode($template)); ?>)">
              <i class="bi bi-pencil"></i> Edit
            </button>
            <button class="btn secondary" onclick="previewTemplate(<?php echo (int)$template['id']; ?>)">
              <i class="bi bi-eye"></i> Preview
            </button>
            <form method="post" action="?panel=application_forms" style="display:inline">
              <input type="hidden" name="action" value="duplicate_template">
              <input type="hidden" name="template_id" value="<?php echo (int)$template['id']; ?>">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
              <button class="btn secondary" type="submit"><i class="bi bi-files"></i> Duplicate</button>
            </form>
            <form method="post" action="?panel=application_forms" style="display:inline" onsubmit="return confirm('Toggle template status?')">
              <input type="hidden" name="action" value="toggle_template">
              <input type="hidden" name="template_id" value="<?php echo (int)$template['id']; ?>">
              <input type="hidden" name="is_active" value="<?php echo $template['is_active'] ? 0 : 1; ?>">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
              <button class="btn secondary" type="submit">
                <i class="bi bi-toggle2-<?php echo $template['is_active']?'on':'off'; ?>"></i>
              </button>
            </form>
            <form method="post" action="?panel=application_forms" style="display:inline" data-confirm="Delete this template? This cannot be undone.">
              <input type="hidden" name="action" value="delete_template">
              <input type="hidden" name="template_id" value="<?php echo (int)$template['id']; ?>">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
              <button class="btn secondary" type="submit" style="color:#ef4444">
                <i class="bi bi-trash"></i>
              </button>
            </form>
          </div>
          
          <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--border)">
            <div class="muted" style="font-size:12px">
              Created: <?php echo date('M j, Y', strtotime($template['created_at'])); ?>
              <?php if($template['updated_at'] !== $template['created_at']): ?>
                • Updated: <?php echo date('M j, Y', strtotime($template['updated_at'])); ?>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- Form Builder Modal -->
<div id="formBuilderModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999">
  <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:var(--card);border-radius:16px;padding:24px;min-width:800px;max-width:95vw;max-height:90vh;overflow:auto">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
      <h3>Form Builder</h3>
      <button onclick="closeFormBuilder()" style="background:none;border:none;font-size:20px;cursor:pointer">&times;</button>
    </div>
    
    <form method="post" action="?panel=application_forms" id="formBuilderForm">
      <input type="hidden" name="action" value="save_template">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
      <input type="hidden" name="form_structure" id="formStructure">
      
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px">
        <div>
          <label class="form-label">Template Name *</label>
          <input class="input" name="template_name" id="templateName" required>
        </div>
        <div>
          <label class="form-label">Program (Optional)</label>
          <select class="input" name="program_id" id="templateProgram">
            <option value="">General Template</option>
            <?php foreach($programs as $program): ?>
              <option value="<?php echo (int)$program['id']; ?>"><?php echo htmlspecialchars($program['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      
      <div style="margin-bottom:20px">
        <label class="form-label">Description</label>
        <textarea class="input" name="template_description" id="templateDescription" rows="2" placeholder="Brief description of this form template"></textarea>
      </div>
      
      <div style="border:1px solid var(--border);border-radius:8px;padding:16px;background:var(--surface-hover)">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
          <h4 style="margin:0">Form Fields</h4>
          <div style="display:flex;gap:8px">
            <select id="fieldTypeSelect" class="input" style="width:150px">
              <?php foreach($fieldTypes as $value => $label): ?>
                <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
              <?php endforeach; ?>
            </select>
            <button type="button" class="btn" onclick="addField()">
              <i class="bi bi-plus"></i> Add Field
            </button>
          </div>
        </div>
        
        <div id="formFieldsContainer" style="min-height:200px;border:2px dashed var(--border);border-radius:8px;padding:16px">
          <div style="text-align:center;color:var(--muted);padding:40px">
            <div style="font-size:24px;margin-bottom:8px">📝</div>
            <div>No fields added yet. Click "Add Field" to start building your form.</div>
          </div>
        </div>
      </div>
      
      <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:20px">
        <button type="button" class="btn secondary" onclick="closeFormBuilder()">Cancel</button>
        <button type="submit" class="btn">Save Template</button>
      </div>
    </form>
  </div>
</div>

<!-- Preview Modal -->
<div id="previewModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999">
  <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:var(--card);border-radius:16px;padding:24px;min-width:600px;max-width:90vw;max-height:90vh;overflow:auto">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
      <h3>Form Preview</h3>
      <button onclick="closePreview()" style="background:none;border:none;font-size:20px;cursor:pointer">&times;</button>
    </div>
    <div id="previewContent"></div>
  </div>
</div>

<script>
let fieldCounter = 0;
let formStructure = { sections: [] };

function showFormBuilder(templateData = null) {
  const modal = document.getElementById('formBuilderModal');
  const form = document.getElementById('formBuilderForm');
  
  if (templateData) {
    // Edit mode
    form.action = '?panel=application_forms&action=update_template';
    form.innerHTML += '<input type="hidden" name="template_id" value="' + templateData.id + '">';
    document.getElementById('templateName').value = templateData.name;
    document.getElementById('templateDescription').value = templateData.description || '';
    document.getElementById('templateProgram').value = templateData.program_id || '';
    
    try {
      formStructure = JSON.parse(templateData.form_structure);
      renderFormFields();
    } catch(e) {
      formStructure = { sections: [] };
    }
  } else {
    // Create mode
    form.action = '?panel=application_forms&action=save_template';
    form.reset();
    formStructure = { sections: [] };
    renderFormFields();
  }
  
  modal.style.display = 'block';
}

function closeFormBuilder() {
  document.getElementById('formBuilderModal').style.display = 'none';
}

function addField() {
  const fieldType = document.getElementById('fieldTypeSelect').value;
  const fieldId = 'field_' + (++fieldCounter);
  
  const fieldData = {
    id: fieldId,
    type: fieldType,
    label: 'New Field',
    required: false,
    placeholder: '',
    options: fieldType === 'select' || fieldType === 'radio' ? ['Option 1', 'Option 2'] : null
  };
  
  if (!formStructure.sections.length) {
    formStructure.sections.push({
      id: 'section_1',
      title: 'Section 1',
      fields: []
    });
  }
  
  formStructure.sections[0].fields.push(fieldData);
  renderFormFields();
}

function renderFormFields() {
  const container = document.getElementById('formFieldsContainer');
  
  if (!formStructure.sections.length) {
    container.innerHTML = `
      <div style="text-align:center;color:var(--muted);padding:40px">
        <div style="font-size:24px;margin-bottom:8px">📝</div>
        <div>No fields added yet. Click "Add Field" to start building your form.</div>
      </div>
    `;
    return;
  }
  
  let html = '';
  formStructure.sections.forEach((section, sectionIndex) => {
    html += `
      <div style="border:1px solid var(--border);border-radius:8px;padding:16px;margin-bottom:16px;background:white">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
          <input type="text" class="input" value="${section.title}" style="font-weight:500" onchange="updateSectionTitle(${sectionIndex}, this.value)">
          <button type="button" class="btn secondary" onclick="removeSection(${sectionIndex})" style="color:#ef4444">
            <i class="bi bi-trash"></i>
          </button>
        </div>
        <div id="section_${sectionIndex}_fields">
    `;
    
    section.fields.forEach((field, fieldIndex) => {
      html += renderField(field, sectionIndex, fieldIndex);
    });
    
    html += `
        </div>
      </div>
    `;
  });
  
  container.innerHTML = html;
  updateFormStructure();
}

function renderField(field, sectionIndex, fieldIndex) {
  // Render non-required dummy inputs in builder to avoid blocking admin submission
  const fieldHtml = {
    text: `<input type="text" class="input" placeholder="${field.placeholder || ''}">`,
    email: `<input type="email" class="input" placeholder="${field.placeholder || ''}">`,
    phone: `<input type="tel" class="input" placeholder="${field.placeholder || ''}">`,
    number: `<input type="number" class="input" placeholder="${field.placeholder || ''}">`,
    date: `<input type="date" class="input">`,
    select: `<select class="input"><option>Select option...</option></select>`,
    textarea: `<textarea class="input" rows="3" placeholder="${field.placeholder || ''}"></textarea>`,
    file: `<input type="file" class="input">`,
    checkbox: `<label><input type="checkbox"> Checkbox option</label>`,
    radio: `<label><input type="radio" name="radio_${field.id}"> Radio option</label>`,
    section: `<div style="font-weight:500;padding:8px 0;border-bottom:1px solid var(--border)">Section Header</div>`
  };
  
  return `
    <div style="margin-bottom:16px;padding:12px;border:1px solid var(--border);border-radius:6px;background:var(--surface-hover)">
      <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:8px">
        <div style="flex:1">
          <input type="text" class="input" value="${field.label}" style="margin-bottom:8px" onchange="updateFieldProperty(${sectionIndex}, ${fieldIndex}, 'label', this.value)">
          <div>${fieldHtml[field.type] || '<span class="muted">Unknown field type</span>'}</div>
        </div>
        <div style="display:flex;gap:4px;margin-left:12px">
          <button type="button" class="btn secondary" onclick="moveField(${sectionIndex}, ${fieldIndex}, -1)" title="Move Up">
            <i class="bi bi-arrow-up"></i>
          </button>
          <button type="button" class="btn secondary" onclick="moveField(${sectionIndex}, ${fieldIndex}, 1)" title="Move Down">
            <i class="bi bi-arrow-down"></i>
          </button>
          <button type="button" class="btn secondary" onclick="removeField(${sectionIndex}, ${fieldIndex})" title="Remove" style="color:#ef4444">
            <i class="bi bi-trash"></i>
          </button>
        </div>
      </div>
      <div style="display:flex;gap:12px;align-items:center;font-size:12px;flex-wrap:wrap">
        <label><input type="checkbox" ${field.required ? 'checked' : ''} onchange="updateFieldProperty(${sectionIndex}, ${fieldIndex}, 'required', this.checked)"> Required</label>
        <label><input type="checkbox" ${field.multiple ? 'checked' : ''} onchange="updateFieldProperty(${sectionIndex}, ${fieldIndex}, 'multiple', this.checked)"> Multiple (select/checkbox)</label>
        <input type="text" class="input" placeholder="Placeholder text" value="${field.placeholder || ''}" style="flex:1;min-width:200px" onchange="updateFieldProperty(${sectionIndex}, ${fieldIndex}, 'placeholder', this.value)">
      </div>
      ${['select','radio','checkbox'].includes(field.type) ? `
      <div style="margin-top:8px">
        <div style="font-size:12px;color:var(--muted);margin-bottom:4px">Choices</div>
        <div id="opts_${sectionIndex}_${fieldIndex}"></div>
        <div style="display:flex;gap:8px;margin-top:8px">
          <input type="text" class="input" id="newOpt_${sectionIndex}_${fieldIndex}" placeholder="Add choice label">
          <button type="button" class="btn secondary" onclick="addOption(${sectionIndex}, ${fieldIndex})"><i class="bi bi-plus"></i> Add</button>
        </div>
      </div>
      ` : ''}
    </div>
  `;
}

function updateFieldProperty(sectionIndex, fieldIndex, property, value) {
  formStructure.sections[sectionIndex].fields[fieldIndex][property] = value;
  updateFormStructure();
  if (['options','label','type','multiple'].includes(property)) {
    renderFormFields();
  }
}

function removeField(sectionIndex, fieldIndex) {
  formStructure.sections[sectionIndex].fields.splice(fieldIndex, 1);
  renderFormFields();
}

function addOption(sectionIndex, fieldIndex) {
  const input = document.getElementById(`newOpt_${sectionIndex}_${fieldIndex}`);
  const val = (input?.value || '').trim();
  if (!val) return;
  const field = formStructure.sections[sectionIndex].fields[fieldIndex];
  if (!Array.isArray(field.options)) field.options = [];
  field.options.push(val);
  input.value = '';
  updateFormStructure();
  renderFormFields();
}

function updateSectionTitle(sectionIndex, title) {
  formStructure.sections[sectionIndex].title = title;
  updateFormStructure();
}

function removeSection(sectionIndex) {
  formStructure.sections.splice(sectionIndex, 1);
  renderFormFields();
}

function updateFormStructure() {
  document.getElementById('formStructure').value = JSON.stringify(formStructure);
}

function editTemplate(templateData) {
  showFormBuilder(templateData);
}

function previewTemplate(templateId) {
  try {
    const tpl = (window.__templates || []).find(t => t.id == templateId);
    if (!tpl) {
      document.getElementById('previewContent').innerHTML = '<div class="muted" style="padding:24px;text-align:center">Template not loaded in client. Please use Edit first.</div>';
      document.getElementById('previewModal').style.display = 'block';
      return;
    }
    const structure = JSON.parse(tpl.form_structure || '{"sections":[]}');
    let html = '<div style="padding:8px 0">';
    html += `<h4 style="margin:0 0 12px 0">${tpl.name || 'Form'}</h4>`;
    structure.sections.forEach((section, sIdx) => {
      html += `<div style=\"margin-bottom:16px\"><div style=\"font-weight:600;margin-bottom:8px\">${section.title || ('Section ' + (sIdx+1))}</div>`;
      (section.fields||[]).forEach((f, i) => {
        const id = `pv_${sIdx}_${i}`;
        const label = `<label class=\"form-label\" for=\"${id}\">${f.label || ('Field ' + (i+1))}${f.required?' *':''}</label>`;
        if (f.type==='text' || f.type==='email' || f.type==='phone' || f.type==='number' || f.type==='date') {
          const type = f.type==='phone' ? 'tel' : (f.type==='text'?'text':f.type);
          html += `<div style=\"margin-bottom:10px\">${label}<input id=\"${id}\" class=\"input\" type=\"${type}\" placeholder=\"${f.placeholder||''}\" ${f.required?'required':''}></div>`;
        } else if (f.type==='textarea') {
          html += `<div style=\"margin-bottom:10px\">${label}<textarea id=\"${id}\" class=\"input\" rows=\"3\" placeholder=\"${f.placeholder||''}\" ${f.required?'required':''}></textarea></div>`;
        } else if (f.type==='file') {
          html += `<div style=\"margin-bottom:10px\">${label}<input id=\"${id}\" class=\"input\" type=\"file\" ${f.required?'required':''}></div>`;
        } else if (f.type==='select') {
          const multiple = f.multiple ? ' multiple' : '';
          const opts = (f.options||[]).map(o => `<option>${o}</option>`).join('');
          html += `<div style=\"margin-bottom:10px\">${label}<select id=\"${id}\" class=\"input\"${multiple}>${opts||'<option>—</option>'}</select></div>`;
        } else if (f.type==='checkbox') {
          const opts = (f.options||['Option']).map((o, k) => `<label style=\"display:inline-flex;gap:6px;align-items:center;margin-right:10px\"><input type=\"checkbox\" name=\"${id}\"> ${o}</label>`).join('');
          html += `<div style=\"margin-bottom:10px\">${label}<div>${opts}</div></div>`;
        } else if (f.type==='radio') {
          const opts = (f.options||['Option']).map((o, k) => `<label style=\"display:inline-flex;gap:6px;align-items:center;margin-right:10px\"><input type=\"radio\" name=\"${id}\"> ${o}</label>`).join('');
          html += `<div style=\"margin-bottom:10px\">${label}<div>${opts}</div></div>`;
        }
      });
      html += `</div>`;
    });
    html += `<div><button class=\"btn\" type=\"button\" disabled>Submit (preview)</button></div>`;
    html += '</div>';
    document.getElementById('previewContent').innerHTML = html;
  } catch (e) {
    document.getElementById('previewContent').innerHTML = '<div class="muted" style="padding:24px;text-align:center">Unable to render preview.</div>';
  }
  document.getElementById('previewModal').style.display = 'block';
}

function closePreview() {
  document.getElementById('previewModal').style.display = 'none';
}

// Close modals when clicking outside
document.addEventListener('click', function(e) {
  if (e.target.id === 'formBuilderModal') closeFormBuilder();
  if (e.target.id === 'previewModal') closePreview();
});

// Expose templates for preview on page (lightweight)
try { window.__templates = <?php echo json_encode($templates, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); ?>; } catch(e){}
</script>
