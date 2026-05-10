<?php
/**
 * CMDB Settings — Classes, Relationship Types, AI Integration
 */
session_start();
require_once '../../config.php';

if (!isset($_SESSION['analyst_id'])) {
    header('Location: ../../login.php');
    exit;
}

$analyst_name = $_SESSION['analyst_name'] ?? 'Analyst';
$current_page = 'settings';
$path_prefix = '../../';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FreeITSM - CMDB Settings</title>
    <link rel="stylesheet" href="../../assets/css/inbox.css">
    <script src="../../assets/js/toast.js"></script>
    <style>
        body { overflow: auto; height: auto; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 24px auto; padding: 0 20px; }
        .tabs {
            display: flex;
            gap: 4px;
            border-bottom: 2px solid #e5e7eb;
            margin-bottom: 24px;
        }
        .tab {
            background: transparent;
            border: none;
            padding: 12px 20px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            color: #6b7280;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: color 0.15s, border-color 0.15s;
        }
        .tab:hover { color: #be185d; }
        .tab.active { color: #be185d; border-bottom-color: #be185d; }
        .tab-content { display: none; background: white; padding: 24px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .tab-content.active { display: block; }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        .section-header h2 { font-size: 18px; color: #111827; margin: 0; }
        .add-btn {
            background: #be185d;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            font-size: 13px;
        }
        .add-btn:hover { background: #9d174d; }

        table { width: 100%; border-collapse: collapse; }
        thead th {
            text-align: left;
            padding: 10px 12px;
            font-size: 12px;
            text-transform: uppercase;
            color: #6b7280;
            border-bottom: 1px solid #e5e7eb;
            font-weight: 600;
        }
        tbody td { padding: 12px; border-bottom: 1px solid #f3f4f6; font-size: 14px; color: #1f2937; }
        tbody tr:hover { background: #fafafa; }

        .action-btn {
            background: none;
            border: 1px solid #ddd;
            color: #666;
            cursor: pointer;
            padding: 6px;
            margin-right: 4px;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .action-btn:hover { background: #fdf2f8; border-color: #be185d; color: #be185d; }
        .action-btn.delete { color: #d13438; }
        .action-btn.delete:hover { background: #fdf3f3; border-color: #d13438; color: #a00; }
        .action-btn svg { width: 14px; height: 14px; }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            font-size: 12px;
            border-radius: 999px;
            background: #f3f4f6;
            color: #374151;
        }
        .badge.active { background: #dcfce7; color: #166534; }
        .badge.inactive { background: #fee2e2; color: #991b1b; }
        .badge.clickable { cursor: pointer; }
        .badge.clickable:hover { background: #fce7f3; color: #be185d; }
        .badge.type { background: #ede9fe; color: #6d28d9; font-family: 'Consolas', monospace; }

        /* Modal styling */
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 1000; }
        .modal.active { display: flex; align-items: center; justify-content: center; }
        .modal-content { background: white; border-radius: 8px; width: 600px; max-width: 95vw; max-height: 90vh; overflow-y: auto; }
        .modal-content.wide { width: 900px; }
        .modal-header { padding: 18px 24px; border-bottom: 1px solid #e5e7eb; font-weight: 600; font-size: 16px; }
        .modal-body { padding: 24px; }
        .modal-actions {
            padding: 16px 24px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        .form-group { margin-bottom: 16px; }
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #374151;
            margin-bottom: 6px;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 14px;
            font-family: inherit;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #be185d;
            box-shadow: 0 0 0 3px rgba(190, 24, 93, 0.1);
        }
        .form-group small { color: #6b7280; font-size: 12px; display: block; margin-top: 4px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-check { display: flex; align-items: center; gap: 8px; font-size: 14px; }
        .form-check input[type="checkbox"] { width: auto; }

        .btn { padding: 9px 18px; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 500; border: 1px solid transparent; }
        .btn-primary { background: #be185d; color: white; }
        .btn-primary:hover { background: #9d174d; }
        .btn-secondary { background: white; color: #374151; border-color: #d1d5db; }
        .btn-secondary:hover { background: #f9fafb; }
        .btn-test { background: #6b7280; color: white; }
        .btn-test:hover { background: #4b5563; }

        .empty-row { text-align: center; padding: 30px; color: #9ca3af; font-style: italic; }
        .key-hint { font-family: 'Consolas', 'Monaco', monospace; font-size: 12px; color: #6b7280; }

        .test-result { margin-top: 16px; padding: 10px 14px; border-radius: 4px; font-size: 13px; display: none; }
        .test-result.success { background: #dcfce7; color: #166534; display: block; }
        .test-result.error { background: #fee2e2; color: #991b1b; display: block; }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container">
        <div class="tabs">
            <button class="tab active" data-tab="classes" onclick="switchTab('classes')">Classes</button>
            <button class="tab" data-tab="relationship-types" onclick="switchTab('relationship-types')">Relationship Types</button>
            <button class="tab" data-tab="ai" onclick="switchTab('ai')">AI Integration</button>
        </div>

        <!-- Classes Tab -->
        <div class="tab-content active" id="classes-tab">
            <div class="section-header">
                <h2>Classes</h2>
                <button class="add-btn" onclick="openClassModal()">Add</button>
            </div>
            <p style="color: #6b7280; font-size: 13px; margin-bottom: 16px; max-width: 720px;">
                A <strong>class</strong> is a type of thing in your estate (e.g. Server, Database, Application). Each class has its own user-defined properties. Click <strong>Properties</strong> on any row to manage its property definitions.
            </p>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Key</th>
                        <th>Description</th>
                        <th>Properties</th>
                        <th>Order</th>
                        <th>Active</th>
                        <th style="width: 130px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="classesTableBody">
                    <tr><td colspan="7" class="empty-row">Loading…</td></tr>
                </tbody>
            </table>
        </div>

        <!-- Relationship Types Tab -->
        <div class="tab-content" id="relationship-types-tab">
            <div class="section-header">
                <h2>Relationship Types</h2>
                <button class="add-btn" onclick="openRelTypeModal()">Add</button>
            </div>
            <p style="color: #6b7280; font-size: 13px; margin-bottom: 16px; max-width: 720px;">
                Named verbs that link any two objects (separate from the parent/child hierarchy). Each verb has an <strong>inverse</strong> shown when viewing the other side of the link — e.g. <em>"depends on"</em> ↔ <em>"is depended on by"</em>.
            </p>
            <table>
                <thead>
                    <tr>
                        <th>Verb</th>
                        <th>Inverse Verb</th>
                        <th>Description</th>
                        <th>Order</th>
                        <th>Active</th>
                        <th style="width: 130px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="relTypesTableBody">
                    <tr><td colspan="6" class="empty-row">Loading…</td></tr>
                </tbody>
            </table>
        </div>

        <!-- AI Integration Tab -->
        <div class="tab-content" id="ai-tab">
            <div class="section-header">
                <h2>AI Integration</h2>
            </div>
            <p style="max-width: 700px; color: #555; font-size: 14px;">
                Powers the v1 CMDB AI features: <strong>object summaries</strong> at the top of every detail page, <strong>property suggestions</strong> when creating a new class, and <strong>relationship suggestions</strong> on the object detail view.
            </p>
            <p style="max-width: 700px; color: #555; font-size: 14px;">
                Uses its own Anthropic API key (separate from RFP AI / Knowledge AI / Reply Cleanup) so usage shows as a discrete line on the Anthropic billing dashboard.
            </p>

            <form id="aiForm" style="max-width: 600px; margin-top: 24px;" onsubmit="saveAiSettings(event)">
                <div class="form-group">
                    <label for="aiApiKey">Anthropic API Key</label>
                    <input type="password" id="aiApiKey" autocomplete="off" placeholder="sk-ant-...">
                    <small>Encrypted at rest. Leave the masked value untouched to keep the existing key.</small>
                </div>

                <div class="form-group">
                    <label for="aiModel">Model</label>
                    <select id="aiModel">
                        <option value="claude-haiku-4-5-20251001">Claude Haiku 4.5 (recommended — fast and cheap)</option>
                        <option value="claude-sonnet-4-6">Claude Sonnet 4.6</option>
                        <option value="claude-opus-4-7">Claude Opus 4.7</option>
                    </select>
                    <small>Haiku handles summaries and suggestions comfortably.</small>
                </div>

                <div class="form-group">
                    <label for="aiCustomInstructions">Custom Instructions <span style="color: #999; font-weight: normal;">(optional)</span></label>
                    <textarea id="aiCustomInstructions" rows="5" maxlength="4000"
                              placeholder="e.g. Use British English spellings.&#10;Refer to the company as 'BillCorp'.&#10;When suggesting properties, prefer ITIL terminology."></textarea>
                    <small>Appended to every CMDB AI prompt. Use plain English — labels and verbs in the CMDB are sent to the model verbatim.</small>
                </div>

                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="submit" class="btn btn-primary">Save</button>
                    <button type="button" class="btn btn-test" onclick="testAiKey()">Test connection</button>
                </div>

                <div id="aiTestResult" class="test-result"></div>
            </form>
        </div>
    </div>

    <!-- Class Add/Edit Modal -->
    <div class="modal" id="classModal">
        <div class="modal-content">
            <div class="modal-header" id="classModalTitle">Add Class</div>
            <div class="modal-body">
                <form id="classForm" onsubmit="saveClass(event)">
                    <input type="hidden" id="classId">
                    <div class="form-group">
                        <label for="className">Name *</label>
                        <input type="text" id="className" required maxlength="150" placeholder="e.g. Database">
                        <small>What an analyst sees in lists and forms. Edit freely later.</small>
                    </div>
                    <div class="form-group">
                        <label for="classKey">Key</label>
                        <input type="text" id="classKey" maxlength="100" placeholder="auto-generated from Name">
                        <small class="key-hint">Immutable identifier used in storage and AI prompts. Auto-generated from Name; edit only if you have a strong reason.</small>
                    </div>
                    <div class="form-group">
                        <label for="classDescription">Description</label>
                        <textarea id="classDescription" rows="2" maxlength="500" placeholder="Short description of what this class represents"></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="classDisplayOrder">Display Order</label>
                            <input type="number" id="classDisplayOrder" value="0">
                        </div>
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <label class="form-check">
                                <input type="checkbox" id="classIsActive" checked> Active
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeClassModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveClass()">Save</button>
            </div>
        </div>
    </div>

    <!-- Properties Manager Modal (shows props for a single class) -->
    <div class="modal" id="propsModal">
        <div class="modal-content wide">
            <div class="modal-header">
                Properties — <span id="propsModalClassName"></span>
            </div>
            <div class="modal-body">
                <div class="section-header">
                    <h2 style="font-size: 14px;">Properties for this class</h2>
                    <button class="add-btn" onclick="openPropertyModal()">Add Property</button>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Label</th>
                            <th>Key</th>
                            <th>Type</th>
                            <th>Target Class</th>
                            <th>Required</th>
                            <th>Order</th>
                            <th style="width: 130px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="propsTableBody">
                        <tr><td colspan="7" class="empty-row">Loading…</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closePropsModal()">Done</button>
            </div>
        </div>
    </div>

    <!-- Property Add/Edit Modal -->
    <div class="modal" id="propertyModal">
        <div class="modal-content">
            <div class="modal-header" id="propertyModalTitle">Add Property</div>
            <div class="modal-body">
                <form id="propertyForm" onsubmit="saveProperty(event)">
                    <input type="hidden" id="propertyId">
                    <div class="form-group">
                        <label for="propertyLabel">Label *</label>
                        <input type="text" id="propertyLabel" required maxlength="150" placeholder="e.g. Owner">
                        <small>What an analyst sees on the object form. Edit freely later.</small>
                    </div>
                    <div class="form-group">
                        <label for="propertyKey">Key</label>
                        <input type="text" id="propertyKey" maxlength="100" placeholder="auto-generated from Label">
                        <small class="key-hint">Immutable identifier. Auto-generated; edit only if you have a strong reason.</small>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="propertyType">Type *</label>
                            <select id="propertyType" required onchange="onPropertyTypeChange()">
                                <option value="text">Text</option>
                                <option value="number">Number</option>
                                <option value="date">Date</option>
                                <option value="boolean">Yes/No</option>
                                <option value="dropdown">Dropdown</option>
                                <option value="object_ref">Object Reference</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="propertyDisplayOrder">Display Order</label>
                            <input type="number" id="propertyDisplayOrder" value="0">
                        </div>
                    </div>
                    <div class="form-group" id="targetClassGroup" style="display: none;">
                        <label for="propertyTargetClass">Target Class *</label>
                        <select id="propertyTargetClass">
                            <option value="">— Select —</option>
                        </select>
                        <small>The class of objects this property can point at.</small>
                    </div>
                    <div class="form-group" id="dropdownOptionsGroup" style="display: none;">
                        <label for="propertyOptions">Dropdown Options</label>
                        <textarea id="propertyOptions" rows="4" placeholder="One option per line"></textarea>
                        <small>List the allowed values, one per line.</small>
                    </div>
                    <div class="form-group">
                        <label class="form-check">
                            <input type="checkbox" id="propertyIsRequired"> Required
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closePropertyModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveProperty()">Save</button>
            </div>
        </div>
    </div>

    <!-- Relationship Type Add/Edit Modal -->
    <div class="modal" id="relTypeModal">
        <div class="modal-content">
            <div class="modal-header" id="relTypeModalTitle">Add Relationship Type</div>
            <div class="modal-body">
                <form id="relTypeForm" onsubmit="saveRelType(event)">
                    <input type="hidden" id="relTypeId">
                    <div class="form-group">
                        <label for="relTypeVerb">Verb *</label>
                        <input type="text" id="relTypeVerb" required maxlength="100" placeholder="e.g. depends on">
                        <small>How A relates to B. Use plain English (read by AI features).</small>
                    </div>
                    <div class="form-group">
                        <label for="relTypeInverseVerb">Inverse Verb *</label>
                        <input type="text" id="relTypeInverseVerb" required maxlength="100" placeholder="e.g. is depended on by">
                        <small>The reciprocal — what B sees when looking back at A.</small>
                    </div>
                    <div class="form-group">
                        <label for="relTypeDescription">Description</label>
                        <textarea id="relTypeDescription" rows="2" maxlength="500" placeholder="Short description"></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="relTypeDisplayOrder">Display Order</label>
                            <input type="number" id="relTypeDisplayOrder" value="0">
                        </div>
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <label class="form-check">
                                <input type="checkbox" id="relTypeIsActive" checked> Active
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeRelTypeModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveRelType()">Save</button>
            </div>
        </div>
    </div>

    <script src="settings.js?v=1"></script>
</body>
</html>
