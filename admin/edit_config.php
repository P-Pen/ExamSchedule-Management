<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}
$db = new SQLite3(__DIR__ . '/../data/exam.db');
$id = $_GET['id'] ?? '';
$isEdit = false;
$examName = '';
$message = '';
$room = '';
$examInfos = [['name'=>'','start'=>'','end'=>'']];

if ($id) {
    $stmt = $db->prepare('SELECT content FROM configs WHERE id = :id');
    $stmt->bindValue(':id', $id, SQLITE3_TEXT);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    if ($row) {
        $content = json_decode($row['content'], true);
        $examName = $content['examName'] ?? '';
        $message = $content['message'] ?? '';
        $room = $content['room'] ?? '';
        $examInfos = $content['examInfos'] ?? [['name'=>'','start'=>'','end'=>'']];
        $isEdit = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = trim($_POST['id']);
    $examName = trim($_POST['examName']);
    $message = trim($_POST['message']);
    $room = trim($_POST['room']);
    $names = $_POST['subject_name'] ?? [];
    $starts = $_POST['subject_start'] ?? [];
    $ends = $_POST['subject_end'] ?? [];
    $examInfos = [];
    foreach ($names as $i => $name) {
        if (trim($name) !== '' && isset($starts[$i]) && isset($ends[$i])) {
            // 转换为YYYY-MM-DDTHH:MM:SS格式
            $start = date('Y-m-d\TH:i:s', strtotime($starts[$i]));
            $end = date('Y-m-d\TH:i:s', strtotime($ends[$i]));
            $examInfos[] = [
                'name' => trim($name),
                'start' => $start,
                'end' => $end
            ];
        }
    }
    if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $id)) {
        $msg = "ID格式错误";
    } elseif (!$examName || !$examInfos) {
        $msg = "考试名称和科目不能为空";
    } else {
        $content = [
            'examName' => $examName,
            'message' => $message,
            'room' => $room,
            'examInfos' => $examInfos
        ];
        $stmt = $db->prepare('REPLACE INTO configs (id, content) VALUES (:id, :content)');
        $stmt->bindValue(':id', $id, SQLITE3_TEXT);
        $stmt->bindValue(':content', json_encode($content, JSON_UNESCAPED_UNICODE), SQLITE3_TEXT);
        $stmt->execute();
        header('Location: dashboard.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title><?php echo $isEdit ? '编辑' : '新建'; ?>考试配置</title>
    <link rel="stylesheet" href="../assets/md2-blue.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        body { font-family: Roboto, Arial, sans-serif; background: #f5f7fa; margin: 0; }
        .navbar { background: #1976d2; color: #fff; padding: 16px 24px; display: flex; align-items: center; position: relative; }
        .navbar .material-icons { margin-right: 8px; }
        .navbar .nav-title { display: flex; align-items: center; gap: 8px; }
        .home-btn {
            background: #fff;
            color: #1976d2 !important;
            border: 1px solid #1976d2;
            border-radius: 4px;
            padding: 6px 18px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            margin-left: 24px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none !important;
        }
        .home-btn:hover { background: #e3f0fc; }
        .container {
            max-width: 820px;
            margin: 40px auto;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px #0001;
            padding: 32px 32px 32px 32px;
        }
        .md-btn {
            background: #1976d2;
            color: #fff;
            border: none;
            border-radius: 4px;
            padding: 10px 24px;
            font-size: 16px;
            cursor: pointer;
            text-decoration: none !important;
        }
        .md-btn:hover { background: #1565c0; }
        .md-btn, .md-btn span, .md-btn .material-icons {
            color: #fff !important;
        }
        .add-btn, .add-btn span, .add-btn .material-icons {
            color: #fff !important;
        }
        .del-btn, .del-btn span, .del-btn .material-icons {
            color: #fff !important;
        }
        .back-btn, .back-btn span, .back-btn .material-icons {
            color: #fff !important;
        }
        .input-group { margin-bottom: 24px; }
        label { display: block; margin-bottom: 8px; color: #1976d2; }
        input[type="text"], input[type="datetime-local"] {
            width: 96%;
            padding: 10px;
            border: 1px solid #b3c6e0;
            border-radius: 4px;
            font-size: 16px;
            margin: 0 2%;
            box-sizing: border-box;
        }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; table-layout: fixed; }
        th, td { border: 1px solid #e3eaf2; padding: 8px 6px; text-align: center; }
        th { background: #e3f0fc; }
        .actions { display: flex; gap: 8px; justify-content: center; }
        .msg { color: red; margin-bottom: 16px; }
        .add-btn { background: #43a047; }
        .add-btn:hover { background: #2e7031; }
        .del-btn { background: #e53935; }
        .del-btn:hover { background: #b71c1c; }
        a, a:visited, a:active { text-decoration: none !important; color: inherit; }
        /* 科目表格输入框宽度适配 */
        .subject-input { width: 90%; min-width: 60px; max-width: 180px; margin: 0 auto; }
        .subject-time-input { width: 90%; min-width: 120px; max-width: 180px; margin: 0 auto; }
        .card-title { display: flex; align-items: center; gap: 8px; }
        .secondary-btn { background: #5c6bc0; }
        .secondary-btn:hover { background: #3f51b5; }
        .import-section { background: #f7faff; border: 1px dashed #b3c6e0; border-radius: 6px; padding: 18px 16px; }
        .import-hint { margin: 0 0 12px 0; font-size: 14px; color: #56657f; }
        .import-actions { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 12px; }
        .import-textarea { width: 100%; min-height: 180px; padding: 12px; border: 1px solid #c5d6f2; border-radius: 4px; font-size: 14px; font-family: Consolas, "Courier New", monospace; resize: vertical; display: none; }
        .import-footer { display: flex; align-items: center; gap: 16px; margin-top: 12px; flex-wrap: wrap; }
        .hint { font-size: 13px; color: #d32f2f; min-height: 18px; display: inline-flex; align-items: center; }
        .hint.success { color: #2e7d32; }
        .hint.error { color: #d32f2f; }
        .btn-label { margin-left: 6px; }
        @media (max-width: 900px) {
            .container { max-width: 99vw; padding: 10px; }
            input[type="text"], input[type="datetime-local"] { width: 98%; margin: 0 1%; }
            .import-actions { flex-direction: column; align-items: stretch; }
            .import-footer { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <span class="nav-title">
            <span class="material-icons">edit</span>
            <?php echo $isEdit ? '编辑' : '新建'; ?>考试配置
        </span>
        <a href="../index.php" class="home-btn"><span class="material-icons">home</span>主页</a>
    </div>
    <div class="container">
        <?php if (!empty($msg)): ?>
            <div class="msg"><?php echo htmlspecialchars($msg); ?></div>
        <?php endif; ?>
        <form method="post" id="edit-config-form">
            <div class="input-group">
                <label for="id">配置ID（英文字母、数字、下划线、短横线）</label>
                <input type="text" id="id" name="id" required value="<?php echo htmlspecialchars($id); ?>" <?php if ($isEdit) echo 'readonly'; ?>>
            </div>
            <div class="input-group">
                <label for="examName">考试名称</label>
                <input type="text" id="examName" name="examName" required value="<?php echo htmlspecialchars($examName); ?>">
            </div>
            <div class="input-group">
                <label for="message">提示语</label>
                <input type="text" id="message" name="message" value="<?php echo htmlspecialchars($message); ?>">
            </div>
            <div class="input-group">
                <label for="room">考场号</label>
                <input type="text" id="room" name="room" value="<?php echo htmlspecialchars($room); ?>">
            </div>
            <div class="input-group">
                <label>考试科目安排</label>
                <table>
                    <thead>
                        <tr>
                            <th>科目名称</th>
                            <th>开始时间</th>
                            <th>结束时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody id="subjects-tbody">
                        <!-- rows will be injected by script -->
                    </tbody>
                </table>
                <button type="button" class="md-btn add-btn" id="add-subject-btn">
                    <span class="material-icons" style="vertical-align:middle;">add</span> 添加科目
                </button>
            </div>
            <div class="input-group import-section">
                <label>批量导入 JSON</label>
                <p class="import-hint">可按 README 中的示例 JSON 一次性填充考试安排。</p>
                <div class="import-actions">
                    <button type="button" class="md-btn secondary-btn" id="json-file-btn">
                        <span class="material-icons">upload_file</span>
                        <span class="btn-label">上传 JSON 文件</span>
                    </button>
                    <button type="button" class="md-btn secondary-btn" id="json-text-toggle">
                        <span class="material-icons">content_paste</span>
                        <span class="btn-label">粘贴 JSON</span>
                    </button>
                </div>
                <textarea id="json-textarea" class="import-textarea" placeholder='{"examName":"期末考试","message":"请提前10分钟进入考场","room":"room301","examInfos":[{"name":"数学","start":"2023-12-01T09:00:00","end":"2023-12-01T11:00:00"}]}'></textarea>
                <div class="import-footer">
                    <span id="import-msg" class="hint"></span>
                    <button type="button" class="md-btn secondary-btn" id="apply-json-btn" style="display:none;">
                        <span class="material-icons">check_circle</span>
                        <span class="btn-label">应用到表单</span>
                    </button>
                </div>
                <input type="file" id="json-file-input" accept="application/json" style="display:none;">
            </div>
            <button type="submit" class="md-btn">
                <span class="material-icons" style="vertical-align:middle;">save</span> 保存
            </button>
            <a href="dashboard.php" class="md-btn" style="background:#888;margin-left:16px;" id="back-dashboard-btn">
                <span class="material-icons" style="vertical-align:middle;">arrow_back</span> 返回
            </a>
        </form>
    </div>
    <script>
    const initialExamInfos = <?php echo json_encode($examInfos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    document.addEventListener('DOMContentLoaded', () => {
        const subjectsTbody = document.getElementById('subjects-tbody');
        const addSubjectBtn = document.getElementById('add-subject-btn');
        const jsonTextToggle = document.getElementById('json-text-toggle');
        const jsonTextArea = document.getElementById('json-textarea');
        const applyJsonBtn = document.getElementById('apply-json-btn');
        const jsonFileBtn = document.getElementById('json-file-btn');
        const jsonFileInput = document.getElementById('json-file-input');
        const importMsg = document.getElementById('import-msg');
        const form = document.getElementById('edit-config-form');
        const backBtn = document.getElementById('back-dashboard-btn');
        const examNameInput = document.getElementById('examName');
        const messageInput = document.getElementById('message');
        const roomInput = document.getElementById('room');
        const jsonToggleIcon = jsonTextToggle.querySelector('.material-icons');
    const jsonToggleLabel = jsonTextToggle.querySelector('.btn-label');

        let isDirty = false;

        jsonTextArea.style.display = 'none';
        function setDirty(value = true) {
            isDirty = value;
        }

        document.addEventListener('input', event => {
            if (!event.target.closest('#edit-config-form')) return;
            if (event.target === jsonTextArea) return;
            setDirty();
        });

        form.addEventListener('submit', () => setDirty(false));

        backBtn.addEventListener('click', event => {
            if (isDirty && !confirm('有未保存的更改，确定要返回吗？')) {
                event.preventDefault();
            }
        });

        function toDateTimeInputValue(value) {
            if (!value) return '';
            if (typeof value === 'string') {
                const trimmed = value.trim();
                if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/.test(trimmed)) {
                    return trimmed.slice(0, 19);
                }
            }
            const date = new Date(value);
            if (Number.isNaN(date.getTime())) return '';
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            const seconds = String(date.getSeconds()).padStart(2, '0');
            return `${year}-${month}-${day}T${hours}:${minutes}:${seconds}`;
        }

        function createSubjectRow(info = {}) {
            const row = document.createElement('tr');

            const nameCell = document.createElement('td');
            const nameInput = document.createElement('input');
            nameInput.type = 'text';
            nameInput.name = 'subject_name[]';
            nameInput.required = true;
            nameInput.className = 'subject-input';
            nameInput.value = info.name || '';
            nameCell.appendChild(nameInput);
            row.appendChild(nameCell);

            const startCell = document.createElement('td');
            const startInput = document.createElement('input');
            startInput.type = 'datetime-local';
            startInput.name = 'subject_start[]';
            startInput.required = true;
            startInput.className = 'subject-time-input';
            startInput.value = toDateTimeInputValue(info.start);
            startCell.appendChild(startInput);
            row.appendChild(startCell);

            const endCell = document.createElement('td');
            const endInput = document.createElement('input');
            endInput.type = 'datetime-local';
            endInput.name = 'subject_end[]';
            endInput.required = true;
            endInput.className = 'subject-time-input';
            endInput.value = toDateTimeInputValue(info.end);
            endCell.appendChild(endInput);
            row.appendChild(endCell);

            const actionCell = document.createElement('td');
            actionCell.className = 'actions';
            const deleteBtn = document.createElement('button');
            deleteBtn.type = 'button';
            deleteBtn.className = 'md-btn del-btn';
            const deleteIcon = document.createElement('span');
            deleteIcon.className = 'material-icons';
            deleteIcon.textContent = 'delete';
            deleteBtn.appendChild(deleteIcon);
            deleteBtn.addEventListener('click', () => {
                row.remove();
                if (!subjectsTbody.querySelector('tr')) {
                    addRow({}, false);
                }
                setDirty();
            });
            actionCell.appendChild(deleteBtn);
            row.appendChild(actionCell);

            return row;
        }

        function addRow(info = {}, markDirty = true) {
            const row = createSubjectRow(info);
            subjectsTbody.appendChild(row);
            if (markDirty) {
                setDirty();
            }
        }

        function renderRows(infos = [], markDirty = true) {
            subjectsTbody.innerHTML = '';
            if (!infos.length) {
                addRow({}, false);
            } else {
                infos.forEach(info => addRow(info, false));
            }
            if (markDirty) {
                setDirty();
            }
        }

        function clearImportMessage() {
            importMsg.textContent = '';
            importMsg.className = 'hint';
        }

        function showImportMessage(message, type = 'error') {
            importMsg.textContent = message;
            importMsg.className = `hint ${type}`;
        }

        function applyJsonConfig(config) {
            if (!config || typeof config !== 'object') {
                throw new Error('JSON 结构不正确');
            }
            if (!Array.isArray(config.examInfos) || !config.examInfos.length) {
                throw new Error('examInfos 不能为空');
            }

            const normalizedInfos = config.examInfos.map(info => ({
                name: info.name || '',
                start: info.start || '',
                end: info.end || ''
            }));

            if (config.examName) {
                examNameInput.value = config.examName;
            }
            messageInput.value = config.message || '';
            roomInput.value = config.room || '';

            renderRows(normalizedInfos);
            setDirty();
        }

        function handleJsonTextApply() {
            clearImportMessage();
            if (!jsonTextArea.value.trim()) {
                showImportMessage('请先粘贴 JSON 文本。');
                return;
            }
            try {
                const config = JSON.parse(jsonTextArea.value);
                applyJsonConfig(config);
                showImportMessage('导入成功，已填充表单，可继续调整。', 'success');
            } catch (error) {
                showImportMessage(`解析失败：${error.message}`);
            }
        }

        addSubjectBtn.addEventListener('click', () => addRow());

        jsonTextToggle.addEventListener('click', () => {
            const willShow = jsonTextArea.style.display === 'none';
            jsonTextArea.style.display = willShow ? 'block' : 'none';
            applyJsonBtn.style.display = willShow ? 'inline-flex' : 'none';
            jsonToggleIcon.textContent = willShow ? 'close' : 'content_paste';
            jsonToggleLabel.textContent = willShow ? '收起文本框' : '粘贴 JSON';
            if (!willShow) {
                jsonTextArea.value = '';
                clearImportMessage();
            }
        });

        applyJsonBtn.addEventListener('click', handleJsonTextApply);

        jsonFileBtn.addEventListener('click', () => jsonFileInput.click());

        jsonFileInput.addEventListener('change', event => {
            clearImportMessage();
            const file = event.target.files[0];
            event.target.value = '';
            if (!file) return;

            const reader = new FileReader();
            reader.onload = e => {
                try {
                    const config = JSON.parse(e.target.result);
                    applyJsonConfig(config);
                    showImportMessage(`已从文件 "${file.name}" 导入。`, 'success');
                } catch (error) {
                    showImportMessage(`文件解析失败：${error.message}`);
                }
            };
            reader.onerror = () => {
                showImportMessage('读取文件时出错，请重试。');
            };
            reader.readAsText(file);
        });

        renderRows(Array.isArray(initialExamInfos) ? initialExamInfos : [], false);
    });
    </script>
</body>
</html>
