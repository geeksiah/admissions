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
  
  // Insert default form template
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
  
  $stmt = $pdo->prepare("INSERT IGNORE INTO form_templates (name, description, program_id, form_structure) VALUES (?, ?, ?, ?)");
  $stmt->execute([$defaultTemplate['name'], $defaultTemplate['description'], $defaultTemplate['program_id'], $defaultTemplate['form_structure']]);
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
            <form method="post" action="?panel=application_forms" style="display:inline" onsubmit="return confirm('Toggle template status?')">
              <input type="hidden" name="action" value="toggle_template">
              <input type="hidden" name="template_id" value="<?php echo (int)$template['id']; ?>">
              <input type="hidden" name="is_active" value="<?php echo $template['is_active'] ? 0 : 1; ?>">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
              <button class="btn secondary" type="submit">
                <i class="bi bi-toggle2-<?php echo $template['is_active']?'on':'off'; ?>"></i>
              </button>
            </form>
            <form method="post" action="?panel=application_forms" style="display:inline" onsubmit="return confirm('Delete this template? This cannot be undone.')">
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
  const fieldHtml = {
    text: `<input type="text" class="input" placeholder="${field.placeholder || ''}" ${field.required ? 'required' : ''}>`,
    email: `<input type="email" class="input" placeholder="${field.placeholder || ''}" ${field.required ? 'required' : ''}>`,
    phone: `<input type="tel" class="input" placeholder="${field.placeholder || ''}" ${field.required ? 'required' : ''}>`,
    number: `<input type="number" class="input" placeholder="${field.placeholder || ''}" ${field.required ? 'required' : ''}>`,
    date: `<input type="date" class="input" ${field.required ? 'required' : ''}>`,
    select: `<select class="input" ${field.required ? 'required' : ''}><option>Select option...</option></select>`,
    textarea: `<textarea class="input" rows="3" placeholder="${field.placeholder || ''}" ${field.required ? 'required' : ''}></textarea>`,
    file: `<input type="file" class="input" ${field.required ? 'required' : ''}>`,
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
      <div style="display:flex;gap:12px;align-items:center;font-size:12px">
        <label><input type="checkbox" ${field.required ? 'checked' : ''} onchange="updateFieldProperty(${sectionIndex}, ${fieldIndex}, 'required', this.checked)"> Required</label>
        <input type="text" class="input" placeholder="Placeholder text" value="${field.placeholder || ''}" style="flex:1" onchange="updateFieldProperty(${sectionIndex}, ${fieldIndex}, 'placeholder', this.value)">
      </div>
    </div>
  `;
}

function updateFieldProperty(sectionIndex, fieldIndex, property, value) {
  formStructure.sections[sectionIndex].fields[fieldIndex][property] = value;
  updateFormStructure();
}

function removeField(sectionIndex, fieldIndex) {
  formStructure.sections[sectionIndex].fields.splice(fieldIndex, 1);
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
  // In a real implementation, this would fetch the template and render a preview
  document.getElementById('previewContent').innerHTML = `
    <div style="text-align:center;padding:40px;color:var(--muted)">
      <div style="font-size:24px;margin-bottom:8px">👁️</div>
      <div>Form preview would be displayed here.</div>
      <div style="font-size:12px;margin-top:8px">Template ID: ${templateId}</div>
    </div>
  `;
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
</script>
