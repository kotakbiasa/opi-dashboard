<?php
date_default_timezone_set(trim(@file_get_contents('/etc/timezone')) ?: 'Asia/Makassar');
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/SystemMonitor.php';
require_once __DIR__ . '/includes/FileManager.php';

if (!Auth::check()) {
    header("Location: index.php");
    exit;
}

$state = SystemMonitor::getFullState();
$board = $state['board'] ?? [];
$disk = FileManager::getDiskStats();
$rcloneStatus = FileManager::getRcloneStatus();
$initialPath = trim($_GET['path'] ?? '/root/opi-dashboard');
$dirData = FileManager::listDirectory($initialPath);
$currentPage = 'files';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajer Berkas & Rclone Cloud - Orange Pi Zero 2</title>
    <meta name="description" content="Manajer Berkas, Editor Kode & Rclone Cloud Storage untuk Orange Pi Gateway">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" type="image/png" href="assets/orange-pi-logo.png">
    <style>
        .file-breadcrumb-trail {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px;
            background: var(--bg-card);
            box-shadow: var(--nm-inset-sm);
            border-radius: var(--radius-pill);
            padding: 6px 14px;
            font-size: 12px;
            font-family: monospace;
            font-weight: 700;
        }

        .crumb-link {
            color: #0284c7;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 6px;
            border-radius: var(--radius-sm);
            transition: var(--transition-fast);
        }

        .crumb-link:hover {
            background: rgba(2, 132, 199, 0.12);
        }

        .crumb-link.active {
            color: var(--text-heading);
            cursor: default;
            background: transparent;
        }

        /* Tactile Neumorphic Folder & File Cards */
        .folder-grid-item {
            background: var(--bg-card);
            border-radius: var(--radius-md);
            box-shadow: var(--nm-raised-sm);
            border: 1.5px solid rgba(255, 255, 255, 0.85);
            padding: 12px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            transition: var(--transition-fast);
            cursor: pointer;
        }

        .folder-grid-item:hover {
            box-shadow: var(--nm-raised);
            transform: translateY(-2px);
            border-color: #f59e0b;
        }

        .folder-grid-item.selected {
            box-shadow: var(--nm-inset-sm);
            border-color: #f59e0b;
            background: rgba(245, 158, 11, 0.04);
        }

        .file-grid-card {
            background: var(--bg-card);
            border-radius: var(--radius-md);
            box-shadow: var(--nm-raised-sm);
            border: 1.5px solid rgba(255, 255, 255, 0.85);
            padding: 14px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 10px;
            transition: var(--transition-fast);
            cursor: pointer;
            min-height: 110px;
        }

        .file-grid-card:hover {
            box-shadow: var(--nm-raised);
            transform: translateY(-2px);
            border-color: var(--color-primary);
        }

        .file-grid-card.selected {
            box-shadow: var(--nm-inset-sm);
            border-color: var(--color-primary);
            background: rgba(255, 122, 0, 0.04);
        }

        .file-list-row {
            background: var(--bg-card);
            border-radius: var(--radius-md);
            box-shadow: var(--nm-raised-sm);
            border: 1.5px solid rgba(255, 255, 255, 0.85);
            padding: 10px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: var(--transition-fast);
            cursor: pointer;
        }

        .file-list-row:hover {
            box-shadow: var(--nm-raised);
            transform: translateY(-1px);
            border-color: var(--color-primary);
        }

        .file-list-row.selected {
            box-shadow: var(--nm-inset-sm);
            border-color: var(--color-primary);
            background: rgba(255, 122, 0, 0.04);
        }

        .file-badge-icon {
            width: 36px;
            height: 36px;
            border-radius: var(--radius-md);
            background: var(--bg-card);
            box-shadow: var(--nm-raised-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 17px;
            flex-shrink: 0;
        }

        .code-console-area {
            width: 100%;
            height: 500px;
            font-family: 'JetBrains Mono', 'SFMono-Regular', Consolas, Menlo, monospace;
            font-size: 13px;
            line-height: 1.55;
            background: #0f172a;
            color: #f8fafc;
            border: 1.5px solid #334155;
            border-radius: var(--radius-md);
            padding: 16px;
            outline: none;
            resize: vertical;
            white-space: pre;
            tab-size: 4;
        }

        .code-console-area:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 14px rgba(255, 122, 0, 0.35);
        }

        .dropzone-area {
            border: 2px dashed rgba(2, 132, 199, 0.45);
            border-radius: var(--radius-lg);
            background: rgba(2, 132, 199, 0.03);
            padding: 30px 20px;
            text-align: center;
            cursor: pointer;
            transition: var(--transition-fast);
        }

        .dropzone-area:hover {
            background: rgba(2, 132, 199, 0.08);
            border-color: #0284c7;
        }

        .cloud-remote-card {
            background: var(--bg-card);
            border-radius: var(--radius-md);
            box-shadow: var(--nm-raised-sm);
            border: 1.5px solid rgba(255, 255, 255, 0.85);
            padding: 12px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            transition: var(--transition-fast);
            cursor: pointer;
        }

        .cloud-remote-card:hover {
            box-shadow: var(--nm-raised);
            transform: translateY(-2px);
            border-color: #0284c7;
        }
    </style>
</head>
<body class="app-logged-in">

    <!-- 2-Column Wide Cockpit Layout -->
    <div class="app-container app-container-wide">
        <!-- Left Sidebar Dock -->
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <!-- Main Workspace -->
        <main class="main-content">
            <!-- Top Header Bar -->
            <header class="header-bar">
                <div class="header-brand-capsule">
                    <span class="pulse-green-dot"></span>
                    <strong style="font-size: 13px; color: var(--text-heading);">Orange Pi Zero 2</strong>
                    <span class="header-hostname-pill">Berkas & Rclone Cloud</span>
                </div>

                <div class="header-actions" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <button type="button" class="btn-new-device" onclick="openRcloneManagerModal()" title="Konfigurasi & Kelola Rclone Cloud" style="padding: 7px 14px; color: #0284c7;">
                        <i class="bi bi-cloud-arrow-up-fill" style="color: #0284c7;"></i>
                        <span>Rclone Cloud</span>
                    </button>

                    <button type="button" class="btn-new-device" onclick="openNewFolderModal()" title="Buat Direktori Baru" style="padding: 7px 14px;">
                        <i class="bi bi-folder-plus" style="color: #f59e0b;"></i>
                        <span>Folder Baru</span>
                    </button>

                    <button type="button" class="btn-new-device" onclick="openNewFileModal()" title="Buat Berkas Baru" style="padding: 7px 14px;">
                        <i class="bi bi-file-earmark-plus" style="color: #0284c7;"></i>
                        <span>Berkas Baru</span>
                    </button>

                    <button type="button" class="btn-primary-neumorphic" onclick="openUploadModal()" title="Unggah Berkas ke Folder Ini" style="padding: 8px 18px; font-size: 12px;">
                        <i class="bi bi-upload"></i>
                        <span>Unggah Berkas</span>
                    </button>

                    <div class="btn-new-device" style="cursor: default; padding: 7px 14px;" title="Waktu Aktif Sistem">
                        <span style="display:inline-block; width:7px; height:7px; border-radius:50%; background:#10b981; box-shadow:0 0 8px #10b981;"></span>
                        <span>Aktif: <?= htmlspecialchars($board['uptime'] ?? '10m') ?></span>
                    </div>
                </div>
            </header>

            <!-- Storage Capacity HUD Banner -->
            <div class="cellular-hud-hero">
                <div class="hud-operator-identity">
                    <div class="hud-operator-logo-badge" id="bannerStorageIcon" style="color: #0284c7;">
                        <i class="bi bi-folder2-open"></i>
                    </div>
                    <div>
                        <h1 class="hud-carrier-name" id="bannerStorageTitle">Pusat Manajer Berkas <span style="font-size: 15px; font-weight: 700; color: #059669;" id="bannerStorageSubtitle">Partisi Root (/)</span></h1>
                        <div class="hud-tags-row">
                            <span class="hud-tech-pill"><i class="bi bi-hdd-fill"></i> Terpakai: <strong id="diskUsedText"><?= $disk['used_formatted'] ?></strong> (<?= $disk['used_percent'] ?>%)</span>
                            <span class="hud-freq-pill"><i class="bi bi-pie-chart-fill"></i> Tersedia: <strong id="diskFreeText"><?= $disk['free_formatted'] ?></strong></span>
                            <span class="hud-plmn-pill" id="rclonePillStatus"><i class="bi bi-cloud-check-fill" style="color: #38bdf8;"></i> Rclone: <?= $rcloneStatus['installed'] ? 'v' . $rcloneStatus['version'] : 'Nonaktif' ?></span>
                        </div>
                    </div>
                </div>

                <!-- Right Side Disk Progress Bar & Preset Shortcut Buttons -->
                <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <button type="button" class="btn-new-device" onclick="navigateToPath('/root/opi-dashboard')" title="Ke Dashboard Project" style="padding: 7px 14px; font-size: 11.5px;">
                            <i class="bi bi-code-square" style="color: var(--color-primary);"></i>
                            <span>Dashboard</span>
                        </button>
                        <button type="button" class="btn-new-device" onclick="navigateToPath('/root')" title="Ke Direktori /root" style="padding: 7px 14px; font-size: 11.5px;">
                            <i class="bi bi-folder-fill" style="color: #f59e0b;"></i>
                            <span>/root</span>
                        </button>
                        <button type="button" class="btn-new-device" onclick="navigateToPath('/etc')" title="Ke Direktori Konfigurasi /etc" style="padding: 7px 14px; font-size: 11.5px;">
                            <i class="bi bi-gear-fill" style="color: #8b5cf6;"></i>
                            <span>/etc</span>
                        </button>
                        <button type="button" class="btn-new-device" onclick="openRcloneManagerModal()" title="Buka Cloud Remotes" style="padding: 7px 14px; font-size: 11.5px; color: #0284c7;">
                            <i class="bi bi-cloud-plus-fill"></i>
                            <span>☁️ Cloud Remotes</span>
                        </button>
                    </div>

                    <button type="button" class="btn-primary-neumorphic" onclick="reloadCurrentDir()" title="Muat Ulang Berkas" style="padding: 8px 16px; font-size: 11.5px;">
                        <i class="bi bi-arrow-repeat"></i>
                        <span>Segarkan</span>
                    </button>
                </div>
            </div>

            <!-- Path Breadcrumbs, Search, Type Filter & View Controls -->
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; margin-top: 16px;">
                <!-- Breadcrumbs Trail -->
                <div class="file-breadcrumb-trail" id="breadcrumbBar">
                    <i class="bi bi-hdd-network" style="color: #0284c7; margin-right: 4px;"></i>
                    <?php foreach ($dirData['breadcrumbs'] as $idx => $b): ?>
                        <?php if ($idx > 0): ?>
                            <span style="color: var(--text-muted);">/</span>
                        <?php endif; ?>
                        <span class="crumb-link <?= ($b['path'] === $dirData['current_path']) ? 'active' : '' ?>" onclick="navigateToPath('<?= addslashes($b['path']) ?>')">
                            <?= htmlspecialchars($b['name']) ?>
                        </span>
                    <?php endforeach; ?>
                </div>

                <!-- Controls: Category Filter, Search & View Switcher -->
                <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                    <!-- Segmented Filter Switcher -->
                    <div class="nm-segmented-switch">
                        <button type="button" class="nm-seg-btn active" id="btnFilterAll" onclick="setCategoryFilter('all')" style="padding: 6px 12px;">
                            <i class="bi bi-layers-fill"></i>
                            <span>Semua</span>
                        </button>
                        <button type="button" class="nm-seg-btn" id="btnFilterCode" onclick="setCategoryFilter('code')" style="padding: 6px 12px;">
                            <i class="bi bi-code-slash" style="color: #8b5cf6;"></i>
                            <span>Script</span>
                        </button>
                        <button type="button" class="nm-seg-btn" id="btnFilterDoc" onclick="setCategoryFilter('document')" style="padding: 6px 12px;">
                            <i class="bi bi-file-text-fill" style="color: #0284c7;"></i>
                            <span>Dokumen</span>
                        </button>
                        <button type="button" class="nm-seg-btn" id="btnFilterImg" onclick="setCategoryFilter('image')" style="padding: 6px 12px;">
                            <i class="bi bi-image-fill" style="color: #ec4899;"></i>
                            <span>Gambar</span>
                        </button>
                    </div>

                    <!-- Search Input -->
                    <input type="text" id="fileFilterSearch" class="btn-new-device" placeholder="Cari berkas..." onkeyup="handleFilterSearch()" style="text-align: left; padding: 7px 14px; font-size: 11.5px; width: 160px;">

                    <!-- Sort Selector -->
                    <select id="sortSelector" onchange="renderExplorer()" class="btn-new-device" style="padding: 7px 12px; font-size: 11.5px; font-weight: 700; cursor: pointer;">
                        <option value="name">Nama (A-Z)</option>
                        <option value="size">Ukuran</option>
                    </select>

                    <!-- View Switcher -->
                    <div class="nm-segmented-switch">
                        <button type="button" class="nm-seg-btn active" id="btnViewGrid" onclick="switchViewMode('grid')" title="Tampilan Kotak (Grid)" style="padding: 6px 10px;">
                            <i class="bi bi-grid-fill"></i>
                        </button>
                        <button type="button" class="nm-seg-btn" id="btnViewList" onclick="switchViewMode('list')" title="Tampilan Daftar (List)" style="padding: 6px 10px;">
                            <i class="bi bi-list-ul"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Main File Workspace: Asymmetrical Cockpit Grid (Left: 1fr, Right: Compact 260px) -->
            <div style="display: grid; grid-template-columns: minmax(0, 1fr) 260px; gap: 16px; margin-top: 14px;" class="file-explorer-layout">
                <!-- Left: Folder & File Explorer Container (Spacious Main Workspace) -->
                <div class="hud-card-panel" style="display: flex; flex-direction: column; gap: 14px; min-width: 0;">
                    <!-- Section A: Folders Grid -->
                    <div id="sectionFolders">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <div class="hud-section-title">
                                <div class="hud-section-icon" style="color: #f59e0b; width: 24px; height: 24px; font-size: 12px;">
                                    <i class="bi bi-folder-fill"></i>
                                </div>
                                <span style="font-size: 12.5px;">Direktori Folder</span>
                            </div>
                            <span class="room-spec-pill" id="badgeFoldersCount" style="font-size: 10px; font-weight: 800;"><?= $dirData['total_folders'] ?> Folder</span>
                        </div>

                        <!-- Folders Container Grid -->
                        <div id="foldersContainer" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 8px;">
                            <!-- Rendered dynamically -->
                        </div>
                    </div>

                    <!-- Section B: Files Grid / List -->
                    <div id="sectionFiles" style="border-top: 1px dashed rgba(182, 198, 220, 0.45); padding-top: 12px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <div class="hud-section-title">
                                <div class="hud-section-icon" style="color: #0284c7; width: 24px; height: 24px; font-size: 12px;">
                                    <i class="bi bi-files"></i>
                                </div>
                                <span style="font-size: 12.5px;">Daftar Berkas</span>
                            </div>
                            <span class="room-spec-pill" id="badgeFilesCount" style="font-size: 10px; font-weight: 800;"><?= $dirData['total_files'] ?> Berkas (<?= $dirData['total_size_formatted'] ?>)</span>
                        </div>

                        <!-- Files Grid Container -->
                        <div id="filesGridContainer" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 8px;">
                            <!-- Rendered dynamically (Grid View) -->
                        </div>

                        <!-- Files List Container (Hidden by default) -->
                        <div id="filesListContainer" style="display: none; flex-direction: column; gap: 6px;">
                            <!-- Rendered dynamically (List View) -->
                        </div>

                        <!-- Empty State -->
                        <div id="emptyStateBox" style="display: none; text-align: center; color: var(--text-muted); padding: 40px 16px; font-size: 12px;">
                            <i class="bi bi-folder-x" style="font-size: 32px; display: block; margin-bottom: 6px; opacity: 0.5;"></i>
                            Direktori ini kosong. Gunakan tombol di atas untuk membuat berkas atau mengunggah data.
                        </div>
                    </div>
                </div>

                <!-- Right: Compact File Detail & Action Panel -->
                <div class="hud-card-panel" style="display: flex; flex-direction: column; justify-content: space-between; gap: 14px; padding: 18px 16px; min-width: 0;">
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                            <div class="hud-section-title">
                                <div class="hud-section-icon" style="color: #8b5cf6; width: 22px; height: 22px; font-size: 11px;">
                                    <i class="bi bi-info-circle-fill"></i>
                                </div>
                                <span style="font-size: 12px;">Rincian</span>
                            </div>
                            <span class="room-spec-pill" id="detailItemTypeBadge" style="font-size: 9.5px; padding: 1px 6px;">Pilih Item</span>
                        </div>

                        <!-- Compact Item Preview Capsule -->
                        <div style="background: var(--bg-card); box-shadow: var(--nm-inset-sm); border-radius: var(--radius-md); padding: 14px 12px; text-align: center; margin-bottom: 12px;">
                            <div class="file-badge-icon" id="detailAvatarBox" style="width: 40px; height: 40px; font-size: 19px; margin: 0 auto 8px auto; color: var(--color-primary);">
                                <i class="bi bi-file-earmark"></i>
                            </div>
                            <strong style="font-size: 12px; color: var(--text-heading); display: block; word-break: break-all; line-height: 1.35;" id="detailItemTitle">Pilih Berkas / Folder</strong>
                            <span style="font-size: 9.5px; color: var(--text-muted); font-family: monospace; display: block; margin-top: 4px; word-break: break-all;" id="detailItemPath">Klik item di tabel</span>
                        </div>

                        <!-- Compact Properties List -->
                        <div style="display: flex; flex-direction: column; gap: 8px; font-size: 11px;">
                            <div style="display: flex; justify-content: space-between; padding: 6px 10px; background: var(--bg-card); box-shadow: var(--nm-inset-sm); border-radius: var(--radius-sm);">
                                <span style="color: var(--text-muted);">Ukuran:</span>
                                <strong style="color: var(--text-heading); font-family: monospace;" id="detailPropSize">-</strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 6px 10px; background: var(--bg-card); box-shadow: var(--nm-inset-sm); border-radius: var(--radius-sm);">
                                <span style="color: var(--text-muted);">Modifikasi:</span>
                                <strong style="color: var(--text-heading); font-size: 10px;" id="detailPropDate">-</strong>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons with Generous Spacing -->
                    <div id="detailActionButtons" style="display: flex; flex-direction: column; gap: 10px; border-top: 1px dashed rgba(182, 198, 220, 0.45); padding-top: 14px; margin-top: 6px;">
                        <button type="button" class="btn-primary-neumorphic" id="btnQuickEdit" onclick="editSelectedItem()" style="width: 100%; padding: 10px 14px; font-size: 12px; justify-content: center;">
                            <i class="bi bi-pencil-square"></i>
                            <span>Edit Kode</span>
                        </button>

                        <button type="button" class="btn-new-device" id="btnQuickSyncCloud" onclick="openSyncToCloudModal()" style="width: 100%; padding: 8px 10px; font-size: 11.5px; justify-content: center; color: #0284c7;" title="Cadangkan / Sinkronkan ke Rclone Cloud">
                            <i class="bi bi-cloud-arrow-up"></i>
                            <span>Cadangkan ke Cloud</span>
                        </button>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <button type="button" class="btn-new-device" id="btnQuickDownload" onclick="downloadSelectedItem()" style="padding: 8px 10px; font-size: 11.5px; justify-content: center; color: #059669;" title="Unduh Berkas">
                                <i class="bi bi-download"></i>
                                <span>Unduh</span>
                            </button>

                            <button type="button" class="btn-new-device" id="btnQuickRename" onclick="promptRenameSelectedItem()" style="padding: 8px 10px; font-size: 11.5px; justify-content: center; color: #f59e0b;" title="Ubah Nama">
                                <i class="bi bi-cursor-text"></i>
                                <span>Rename</span>
                            </button>
                        </div>

                        <button type="button" class="btn-new-device" id="btnQuickDelete" onclick="deleteSelectedItem()" style="width: 100%; padding: 8px 12px; font-size: 11.5px; justify-content: center; color: #ef4444; margin-top: 2px;" title="Hapus Berkas / Folder">
                            <i class="bi bi-trash"></i>
                            <span>Hapus Item</span>
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 1: CODE & TEXT EDITOR -->
    <!-- ========================================================================= -->
    <div id="modalCodeEditor" class="reboot-modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.7); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(5px);">
        <div class="hud-card-panel" style="max-width: 920px; width: 95%; max-height: 90vh; display: flex; flex-direction: column; padding: 22px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div class="file-badge-icon" style="color: #38bdf8;">
                        <i class="bi bi-code-slash"></i>
                    </div>
                    <div>
                        <h3 style="font-size: 15px; color: var(--text-heading); font-weight: 800;" id="editorFileNameTitle">Editor Berkas</h3>
                        <span style="font-size: 11px; color: var(--text-muted); font-family: monospace;" id="editorFilePathTitle">/root/...</span>
                    </div>
                </div>
                <button type="button" class="btn-round-ctrl" onclick="closeCodeEditor()">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <!-- Code Console Textarea -->
            <textarea id="editorCodeTextarea" class="code-console-area" spellcheck="false" placeholder="Memuat isi berkas..."></textarea>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 14px; padding-top: 12px; border-top: 1px solid rgba(182, 198, 220, 0.4);">
                <span style="font-size: 11px; color: var(--text-muted);">Tekan <strong>Tab (4 Spasi)</strong> & <strong>Ctrl + S</strong> untuk menyimpan langsung</span>
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn-new-device" onclick="closeCodeEditor()">Tutup</button>
                    <button type="button" class="btn-primary-neumorphic" id="btnSaveCodeFile" onclick="handleSaveCodeFile()" style="padding: 8px 18px; font-size: 12.5px;">
                        <i class="bi bi-floppy-fill"></i>
                        <span>Simpan Perubahan</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 2: CREATE NEW FILE -->
    <!-- ========================================================================= -->
    <div id="modalNewFile" class="reboot-modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
        <div class="hud-card-panel" style="max-width: 480px; width: 90%; padding: 22px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">
                <h3 style="font-size: 15px; color: var(--text-heading); font-weight: 800;"><i class="bi bi-file-earmark-plus" style="color: #0284c7;"></i> Buat Berkas Baru</h3>
                <button type="button" class="btn-round-ctrl" onclick="closeNewFileModal()"><i class="bi bi-x-lg"></i></button>
            </div>
            <form onsubmit="handleNewFileSubmit(event)">
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <div>
                        <label style="font-size: 11px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 4px;">NAMA BERKAS:</label>
                        <input type="text" id="inputNewFileName" class="btn-new-device" placeholder="contoh: test.sh, config.json" style="width: 100%; text-align: left; padding: 10px 14px; font-size: 12.5px;" required>
                    </div>
                    <div>
                        <label style="font-size: 11px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 4px;">ISI AWAL (OPSIONAL):</label>
                        <textarea id="inputNewFileContent" class="btn-new-device" rows="4" placeholder="Tuliskan teks/kode awal..." style="width: 100%; text-align: left; padding: 10px 14px; font-size: 12px;"></textarea>
                    </div>
                    <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 6px;">
                        <button type="button" class="btn-new-device" onclick="closeNewFileModal()">Batal</button>
                        <button type="submit" class="btn-primary-neumorphic" style="padding: 8px 18px; font-size: 12px;">Buat Berkas</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 3: CREATE NEW FOLDER -->
    <!-- ========================================================================= -->
    <div id="modalNewFolder" class="reboot-modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
        <div class="hud-card-panel" style="max-width: 440px; width: 90%; padding: 22px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">
                <h3 style="font-size: 15px; color: var(--text-heading); font-weight: 800;"><i class="bi bi-folder-plus" style="color: #f59e0b;"></i> Buat Folder Baru</h3>
                <button type="button" class="btn-round-ctrl" onclick="closeNewFolderModal()"><i class="bi bi-x-lg"></i></button>
            </div>
            <form onsubmit="handleNewFolderSubmit(event)">
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <div>
                        <label style="font-size: 11px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 4px;">NAMA FOLDER:</label>
                        <input type="text" id="inputNewFolderName" class="btn-new-device" placeholder="contoh: backup, scripts, modules" style="width: 100%; text-align: left; padding: 10px 14px; font-size: 12.5px;" required>
                    </div>
                    <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 6px;">
                        <button type="button" class="btn-new-device" onclick="closeNewFolderModal()">Batal</button>
                        <button type="submit" class="btn-primary-neumorphic" style="padding: 8px 18px; font-size: 12px;">Buat Folder</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 4: UPLOAD FILE -->
    <!-- ========================================================================= -->
    <div id="modalUploadFile" class="reboot-modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
        <div class="hud-card-panel" style="max-width: 480px; width: 90%; padding: 22px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">
                <h3 style="font-size: 15px; color: var(--text-heading); font-weight: 800;"><i class="bi bi-upload" style="color: #0284c7;"></i> Unggah Berkas</h3>
                <button type="button" class="btn-round-ctrl" onclick="closeUploadModal()"><i class="bi bi-x-lg"></i></button>
            </div>
            <form onsubmit="handleUploadSubmit(event)">
                <div class="dropzone-area" onclick="document.getElementById('fileUploadInput').click()">
                    <i class="bi bi-cloud-arrow-up-fill" style="font-size: 40px; color: #0284c7; display: block; margin-bottom: 8px;"></i>
                    <strong style="font-size: 13px; color: var(--text-heading); display: block;">Klik untuk Memilih Berkas</strong>
                    <span style="font-size: 11px; color: var(--text-muted); display: block; margin-top: 4px;" id="uploadFileNameLabel">Mendukung format PHP, JS, CSS, JSON, SH, PNG, ZIP, dll.</span>
                    <input type="file" id="fileUploadInput" style="display: none;" onchange="handleFileSelected(this)">
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 14px;">
                    <button type="button" class="btn-new-device" onclick="closeUploadModal()">Batal</button>
                    <button type="submit" class="btn-primary-neumorphic" id="btnSubmitUpload" style="padding: 8px 18px; font-size: 12px;">Unggah Sekarang</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 5: RCLONE CLOUD STORAGE MANAGER -->
    <!-- ========================================================================= -->
    <div id="modalRcloneManager" class="reboot-modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.7); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(5px);">
        <div class="hud-card-panel" style="max-width: 820px; width: 95%; max-height: 90vh; display: flex; flex-direction: column; padding: 22px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div class="file-badge-icon" style="color: #0284c7; width: 42px; height: 42px; font-size: 20px;">
                        <i class="bi bi-cloud-arrow-up-fill"></i>
                    </div>
                    <div>
                        <h3 style="font-size: 15.5px; color: var(--text-heading); font-weight: 800;">Rclone Cloud Storage Hub</h3>
                        <span style="font-size: 11px; color: var(--text-muted);">Integrasi Google Drive, OneDrive, S3, Dropbox, Mega, WebDAV & Nextcloud</span>
                    </div>
                </div>
                <button type="button" class="btn-round-ctrl" onclick="closeRcloneManagerModal()"><i class="bi bi-x-lg"></i></button>
            </div>

            <!-- Segmented Switch for Rclone Hub -->
            <div class="nm-segmented-switch" style="margin-bottom: 14px;">
                <button type="button" class="nm-seg-btn active" id="btnRcloneTabList" onclick="switchRcloneTab('list')">
                    <i class="bi bi-cloud-check-fill" style="color: #0284c7;"></i>
                    <span>Akun Terhubung</span>
                </button>
                <button type="button" class="nm-seg-btn" id="btnRcloneTabLogin" onclick="switchRcloneTab('login')">
                    <i class="bi bi-key-fill" style="color: #10b981;"></i>
                    <span>+ Login / Tambah Akun</span>
                </button>
                <button type="button" class="nm-seg-btn" id="btnRcloneTabConfig" onclick="switchRcloneTab('config')">
                    <i class="bi bi-file-earmark-code-fill" style="color: #8b5cf6;"></i>
                    <span>Editor rclone.conf</span>
                </button>
            </div>

            <!-- TAB 1: CONNECTED REMOTES LIST -->
            <div id="tabRcloneList" style="display: flex; flex-direction: column; gap: 12px; max-height: 55vh; overflow-y: auto; padding-right: 4px;">
                <div id="rcloneRemotesGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 10px;">
                    <!-- Rendered dynamically -->
                </div>

                <div id="rcloneEmptyRemotes" style="text-align: center; color: var(--text-muted); padding: 32px 16px; font-size: 12.5px;">
                    <i class="bi bi-cloud-slash" style="font-size: 38px; display: block; margin-bottom: 8px; opacity: 0.6; color: #0284c7;"></i>
                    Belum ada akun penyimpanan cloud yang terhubung.<br>
                    Gunakan tab <strong>+ Login / Tambah Akun</strong> untuk menghubungkan Google Drive, OneDrive, Nextcloud, atau WebDAV.
                </div>
            </div>

            <!-- TAB 2: INTERACTIVE CLOUD LOGIN WIZARD -->
            <div id="tabRcloneLogin" style="display: none; flex-direction: column; gap: 14px; max-height: 60vh; overflow-y: auto; padding-right: 4px;">
                <form id="formCloudLogin" onsubmit="handleCloudLoginSubmit(event)">
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <!-- Provider Selection Grid -->
                        <div>
                            <label style="font-size: 11px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 6px;">PILIH PENYEDIA CLOUD STORAGE:</label>
                            <select id="cloudLoginType" class="btn-new-device" onchange="handleCloudProviderChange()" style="width: 100%; padding: 8px 12px; font-size: 12px; font-weight: 700; cursor: pointer;">
                                <option value="drive">🟢 Google Drive</option>
                                <option value="onedrive">🔵 Microsoft OneDrive</option>
                                <option value="webdav">🌊 Nextcloud / OwnCloud / WebDAV</option>
                                <option value="s3">⚡ Amazon S3 / MinIO / Cloudflare R2</option>
                                <option value="mega">🔴 Mega.nz</option>
                                <option value="dropbox">📦 Dropbox</option>
                                <option value="sftp">💻 SFTP / SSH VPS Server</option>
                                <option value="ftp">📁 FTP Server</option>
                            </select>
                        </div>

                        <!-- Remote Name -->
                        <div>
                            <label style="font-size: 11px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 4px;">NAMA AKUN / IDENTITAS REMOTE:</label>
                            <input type="text" id="cloudLoginName" class="btn-new-device" placeholder="contoh: gdrive, mynextcloud, backup-vps" style="width: 100%; text-align: left; padding: 8px 12px; font-size: 12px;" required>
                        </div>

                        <!-- DYNAMIC FIELDS CONTAINER -->
                        <div id="cloudDynamicFields" style="display: flex; flex-direction: column; gap: 10px;">
                            <!-- Populated dynamically via JS -->
                        </div>

                        <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 8px; border-top: 1px dashed rgba(182, 198, 220, 0.45); padding-top: 12px;">
                            <button type="button" class="btn-new-device" onclick="switchRcloneTab('list')">Kembali</button>
                            <button type="submit" class="btn-primary-neumorphic" id="btnSubmitCloudLogin" style="padding: 8px 18px; font-size: 12px;">
                                <i class="bi bi-shield-lock-fill"></i>
                                <span>Hubungkan & Simpan Akun</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- TAB 3: DIRECT RCLONE.CONF EDITOR -->
            <div id="tabRcloneConfig" style="display: none; flex-direction: column; gap: 10px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 11px; color: var(--text-muted);">Lokasi: <code style="color: #0284c7;">/root/.config/rclone/rclone.conf</code></span>
                    <button type="button" class="btn-new-device" onclick="insertSampleRcloneTemplate()" style="font-size: 11px; padding: 4px 10px; color: #8b5cf6;">
                        <i class="bi bi-magic"></i> Sisipkan Contoh Template
                    </button>
                </div>
                <textarea id="rcloneConfigTextarea" class="code-console-area" style="height: 340px;" placeholder="[gdrive]&#10;type = drive&#10;scope = drive&#10;token = {...}"></textarea>
                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn-new-device" onclick="closeRcloneManagerModal()">Tutup</button>
                    <button type="button" class="btn-primary-neumorphic" id="btnSaveRcloneConfig" onclick="handleSaveRcloneConfig()" style="padding: 8px 18px; font-size: 12px;">
                        <i class="bi bi-floppy-fill"></i>
                        <span>Simpan Konfigurasi</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 6: SYNC TO CLOUD MODAL -->
    <!-- ========================================================================= -->
    <div id="modalSyncCloud" class="reboot-modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
        <div class="hud-card-panel" style="max-width: 480px; width: 90%; padding: 22px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">
                <h3 style="font-size: 15px; color: var(--text-heading); font-weight: 800;"><i class="bi bi-cloud-arrow-up-fill" style="color: #0284c7;"></i> Cadangkan ke Cloud (Rclone)</h3>
                <button type="button" class="btn-round-ctrl" onclick="closeSyncToCloudModal()"><i class="bi bi-x-lg"></i></button>
            </div>
            <form onsubmit="handleSyncCloudSubmit(event)">
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <div>
                        <label style="font-size: 11px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 4px;">SUMBER LOKAL:</label>
                        <input type="text" id="inputSyncLocalSource" class="btn-new-device" style="width: 100%; text-align: left; padding: 10px 14px; font-size: 12px; font-family: monospace;" readonly>
                    </div>
                    <div>
                        <label style="font-size: 11px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 4px;">TARGET CLOUD REMOTE (e.g. gdrive:Backup/OPI):</label>
                        <input type="text" id="inputSyncRemoteTarget" class="btn-new-device" placeholder="contoh: gdrive:Backup/OrangePi" style="width: 100%; text-align: left; padding: 10px 14px; font-size: 12.5px; font-family: monospace;" required>
                    </div>
                    <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 6px;">
                        <button type="button" class="btn-new-device" onclick="closeSyncToCloudModal()">Batal</button>
                        <button type="submit" class="btn-primary-neumorphic" id="btnSubmitSyncCloud" style="padding: 8px 18px; font-size: 12px;">Mulai Cadangkan</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Toast Notification Container -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- Unified File & Rclone Controller -->
    <script>
        let currentPath = '<?= addslashes($dirData['current_path']) ?>';
        let isCloudMode = false;
        let serverItems = [];
        let selectedItem = null;
        let activeCategory = 'all'; // all, code, document, image
        let activeViewMode = 'grid'; // grid, list
        let currentEditingPath = '';
        let rcloneRemotes = [];

        document.addEventListener('DOMContentLoaded', () => {
            loadRcloneStatus();
            navigateToPath(currentPath);
        });

        async function loadRcloneStatus() {
            try {
                const res = await fetch('api.php?action=get_rclone_status');
                const data = await res.json();
                if (data.success && data.data) {
                    rcloneRemotes = data.data.remotes || [];
                    renderRcloneRemotesList(rcloneRemotes);
                }
            } catch (err) {
                console.error("Rclone status err", err);
            }
        }

        async function navigateToPath(targetPath) {
            currentPath = targetPath;
            isCloudMode = targetPath.includes(':');

            // Update Header Subtitle
            const bannerTitle = document.getElementById('bannerStorageTitle');
            const bannerSubtitle = document.getElementById('bannerStorageSubtitle');
            const bannerIcon = document.getElementById('bannerStorageIcon');

            if (isCloudMode) {
                if (bannerSubtitle) bannerSubtitle.textContent = `Cloud: ${targetPath}`;
                if (bannerIcon) bannerIcon.innerHTML = `<i class="bi bi-cloud-check-fill" style="color: #0284c7;"></i>`;
            } else {
                if (bannerSubtitle) bannerSubtitle.textContent = `Partisi Root (/)`;
                if (bannerIcon) bannerIcon.innerHTML = `<i class="bi bi-folder2-open" style="color: #0284c7;"></i>`;
            }

            try {
                const url = isCloudMode
                    ? `api.php?action=list_rclone_files&remote_path=${encodeURIComponent(targetPath)}`
                    : `api.php?action=list_files&path=${encodeURIComponent(targetPath)}`;

                const res = await fetch(url);
                const json = await res.json();

                if (json.success && json.data) {
                    currentPath = json.data.current_path;
                    serverItems = json.data.items.map(it => {
                        let cat = 'document';
                        const ext = (it.extension || '').toLowerCase();
                        if (['php','js','css','html','htm','sh','py','conf','cfg','service','env','sql'].includes(ext)) {
                            cat = 'code';
                        } else if (['png','jpg','jpeg','svg','gif','webp','ico'].includes(ext)) {
                            cat = 'image';
                        }
                        return { ...it, catType: cat };
                    });

                    // Update storage meter if local
                    if (!isCloudMode && json.disk) {
                        const usedEl = document.getElementById('diskUsedText');
                        const freeEl = document.getElementById('diskFreeText');
                        if (usedEl) usedEl.textContent = json.disk.used_formatted;
                        if (freeEl) freeEl.textContent = json.disk.free_formatted;
                    }

                    renderBreadcrumbs(json.data.breadcrumbs);
                    renderExplorer();
                } else {
                    showToast(json.error || 'Gagal membuka direktori', 'error');
                }
            } catch (err) {
                showToast('Koneksi terputus ke server', 'error');
            }
        }

        function reloadCurrentDir() {
            navigateToPath(currentPath);
            showToast('Memuat ulang berkas...', 'info');
        }

        function renderBreadcrumbs(breadcrumbs) {
            const bar = document.getElementById('breadcrumbBar');
            if (!bar) return;
            let iconClass = isCloudMode ? 'bi-cloud-check-fill' : 'bi-hdd-network';
            let html = `<i class="bi ${iconClass}" style="color: #0284c7; margin-right: 4px;"></i>`;
            breadcrumbs.forEach((b, idx) => {
                if (idx > 0) html += '<span style="color: var(--text-muted);">/</span>';
                const active = (b.path === currentPath) ? 'active' : '';
                html += `<span class="crumb-link ${active}" onclick="navigateToPath('${escapeJs(b.path)}')">${escapeHtml(b.name)}</span>`;
            });
            bar.innerHTML = html;
        }

        function setCategoryFilter(cat) {
            activeCategory = cat;
            document.querySelectorAll('.nm-seg-btn').forEach(b => {
                if (b.id && b.id.startsWith('btnFilter')) b.classList.remove('active');
            });
            const activeBtn = document.getElementById(`btnFilter${cat.charAt(0).toUpperCase() + cat.slice(1)}`);
            if (activeBtn) activeBtn.classList.add('active');
            renderExplorer();
        }

        function switchViewMode(mode) {
            activeViewMode = mode;
            document.getElementById('btnViewGrid')?.classList.toggle('active', mode === 'grid');
            document.getElementById('btnViewList')?.classList.toggle('active', mode === 'list');
            document.getElementById('filesGridContainer').style.display = (mode === 'grid') ? 'grid' : 'none';
            document.getElementById('filesListContainer').style.display = (mode === 'list') ? 'flex' : 'none';
        }

        function handleFilterSearch() {
            renderExplorer();
        }

        function getFilteredItems() {
            const query = (document.getElementById('fileFilterSearch')?.value || '').toLowerCase().trim();
            let items = [...serverItems];

            if (activeCategory !== 'all') {
                items = items.filter(i => i.is_dir || i.catType === activeCategory);
            }

            if (query !== '') {
                items = items.filter(i => i.name.toLowerCase().includes(query));
            }

            const sortBy = document.getElementById('sortSelector')?.value || 'name';
            items.sort((a, b) => {
                if (a.is_dir && !b.is_dir) return -1;
                if (!a.is_dir && b.is_dir) return 1;
                if (sortBy === 'name') return a.name.localeCompare(b.name);
                if (sortBy === 'size') return (b.size || 0) - (a.size || 0);
                return 0;
            });

            return items;
        }

        function renderExplorer() {
            const filtered = getFilteredItems();
            const folders = filtered.filter(i => i.is_dir);
            const files = filtered.filter(i => !i.is_dir);

            // Badge counts
            const bFolders = document.getElementById('badgeFoldersCount');
            const bFiles = document.getElementById('badgeFilesCount');
            if (bFolders) bFolders.textContent = `${folders.length} Folder`;
            if (bFiles) bFiles.textContent = `${files.length} Berkas`;

            // 1. Render Folders
            const fContainer = document.getElementById('foldersContainer');
            let foldersHtml = '';

            // Up Level
            if (isCloudMode) {
                const parts = currentPath.split(':');
                const sub = (parts[1] || '').replace(/^\/+/, '');
                if (sub) {
                    const upSub = sub.split('/').slice(0, -1).join('/');
                    const upPath = parts[0] + ':' + (upSub ? '/' + upSub : '');
                    foldersHtml += `
                        <div class="folder-grid-item" onclick="navigateToPath('${escapeJs(upPath)}')" style="background: rgba(2, 132, 199, 0.04);">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <i class="bi bi-arrow-90deg-up" style="color: #0284c7; font-size: 14px;"></i>
                                <strong style="font-size: 12px; color: var(--text-heading);">.. (Folder Atas Cloud)</strong>
                            </div>
                            <i class="bi bi-chevron-right" style="font-size: 11px; color: var(--text-muted);"></i>
                        </div>
                    `;
                }
            } else if (currentPath !== '/' && currentPath !== '') {
                const parentPath = currentPath.split('/').slice(0, -1).join('/') || '/';
                foldersHtml += `
                    <div class="folder-grid-item" onclick="navigateToPath('${escapeJs(parentPath)}')" style="background: rgba(2, 132, 199, 0.04);">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <i class="bi bi-arrow-90deg-up" style="color: #0284c7; font-size: 14px;"></i>
                            <strong style="font-size: 12px; color: var(--text-heading);">.. (Folder Atas)</strong>
                        </div>
                        <i class="bi bi-chevron-right" style="font-size: 11px; color: var(--text-muted);"></i>
                    </div>
                `;
            }

            folders.forEach(f => {
                const isSelected = selectedItem?.path === f.path;
                foldersHtml += `
                    <div class="folder-grid-item ${isSelected ? 'selected' : ''}" onclick="selectItem('${escapeJs(f.path)}')" ondblclick="navigateToPath('${escapeJs(f.path)}')">
                        <div style="display: flex; align-items: center; gap: 8px; overflow: hidden;">
                            <i class="bi bi-folder-fill" style="color: #f59e0b; font-size: 16px; flex-shrink: 0;"></i>
                            <span style="font-size: 12px; font-weight: 700; color: var(--text-heading); text-overflow: ellipsis; overflow: hidden; white-space: nowrap;">${escapeHtml(f.name)}</span>
                        </div>
                        <i class="bi bi-chevron-right" style="font-size: 11px; color: var(--text-muted);"></i>
                    </div>
                `;
            });
            fContainer.innerHTML = foldersHtml;

            // 2. Render Files (Grid View)
            const fgContainer = document.getElementById('filesGridContainer');
            const flContainer = document.getElementById('filesListContainer');
            const emptyBox = document.getElementById('emptyStateBox');

            if (files.length === 0 && folders.length === 0) {
                if (emptyBox) emptyBox.style.display = 'block';
            } else {
                if (emptyBox) emptyBox.style.display = 'none';
            }

            let gridHtml = '';
            let listHtml = '';

            files.forEach(file => {
                const isSelected = selectedItem?.path === file.path;
                
                // Grid Card
                gridHtml += `
                    <div class="file-grid-card ${isSelected ? 'selected' : ''}" onclick="selectItem('${escapeJs(file.path)}')" ondblclick="handleFileDblClick('${escapeJs(file.path)}', ${file.is_editable})">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div class="file-badge-icon" style="color: ${file.color};">
                                <i class="bi ${file.icon}"></i>
                            </div>
                            <span style="font-size: 10px; font-family: monospace; color: var(--text-muted); font-weight: 700;">${file.size_formatted}</span>
                        </div>
                        <div>
                            <strong style="font-size: 12px; color: var(--text-heading); display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${escapeHtml(file.name)}">${escapeHtml(file.name)}</strong>
                            <div style="display: flex; justify-content: space-between; font-size: 10px; color: var(--text-muted); margin-top: 4px;">
                                <span>${(file.extension || 'file').toUpperCase()}</span>
                                <span>${file.modified.split(',')[0]}</span>
                            </div>
                        </div>
                    </div>
                `;

                // List Row
                listHtml += `
                    <div class="file-list-row ${isSelected ? 'selected' : ''}" onclick="selectItem('${escapeJs(file.path)}')" ondblclick="handleFileDblClick('${escapeJs(file.path)}', ${file.is_editable})">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div class="file-badge-icon" style="color: ${file.color}; width: 30px; height: 30px; font-size: 14px;">
                                <i class="bi ${file.icon}"></i>
                            </div>
                            <strong style="font-size: 12.5px; color: var(--text-heading);">${escapeHtml(file.name)}</strong>
                        </div>
                        <div style="display: flex; align-items: center; gap: 18px; font-size: 11px; color: var(--text-muted);">
                            <span>${file.modified}</span>
                            <strong style="color: var(--text-heading); font-family: monospace;">${file.size_formatted}</strong>
                        </div>
                    </div>
                `;
            });

            fgContainer.innerHTML = gridHtml;
            flContainer.innerHTML = listHtml;
        }

        function selectItem(path) {
            const it = serverItems.find(i => i.path === path);
            if (!it) return;
            selectedItem = it;
            renderExplorer();

            // Update Right Detail Panel
            const badge = document.getElementById('detailItemTypeBadge');
            const avatar = document.getElementById('detailAvatarBox');
            const title = document.getElementById('detailItemTitle');
            const pathEl = document.getElementById('detailItemPath');
            const pSize = document.getElementById('detailPropSize');
            const pDate = document.getElementById('detailPropDate');

            const btnEdit = document.getElementById('btnQuickEdit');
            const btnDownload = document.getElementById('btnQuickDownload');
            const btnSyncCloud = document.getElementById('btnQuickSyncCloud');

            if (badge) badge.textContent = it.is_dir ? 'Folder' : (it.extension ? it.extension.toUpperCase() + ' Berkas' : 'Berkas');
            if (avatar) {
                avatar.style.color = it.color;
                avatar.innerHTML = `<i class="bi ${it.icon}"></i>`;
            }
            if (title) title.textContent = it.name;
            if (pathEl) pathEl.textContent = it.path;
            if (pSize) pSize.textContent = it.size_formatted;
            if (pDate) pDate.textContent = it.modified;

            if (btnEdit) btnEdit.style.display = (it.is_editable && !isCloudMode) ? 'flex' : 'none';
            if (btnDownload) btnDownload.style.display = !it.is_dir ? 'flex' : 'none';
            if (btnSyncCloud) btnSyncCloud.style.display = !isCloudMode ? 'flex' : 'none';
        }

        function handleFileDblClick(path, isEditable) {
            if (isCloudMode) {
                downloadSelectedItem();
            } else if (isEditable) {
                openCodeEditor(path);
            } else {
                showToast(`Berkas: ${path}`, 'info');
            }
        }

        // --- Code Editor Console ---
        async function openCodeEditor(filePath) {
            currentEditingPath = filePath;
            const modal = document.getElementById('modalCodeEditor');
            const nameTitle = document.getElementById('editorFileNameTitle');
            const pathTitle = document.getElementById('editorFilePathTitle');
            const textarea = document.getElementById('editorCodeTextarea');

            if (modal) modal.style.display = 'flex';
            if (nameTitle) nameTitle.textContent = filePath.split('/').pop();
            if (pathTitle) pathTitle.textContent = filePath;
            if (textarea) {
                textarea.value = 'Memuat konten berkas...';
                textarea.disabled = true;
            }

            try {
                const res = await fetch(`api.php?action=read_file&path=${encodeURIComponent(filePath)}`);
                const data = await res.json();
                if (data.success && textarea) {
                    textarea.value = data.content;
                    textarea.disabled = false;
                    textarea.focus();
                } else {
                    showToast(data.error || 'Gagal membaca berkas', 'error');
                    closeCodeEditor();
                }
            } catch (err) {
                showToast('Koneksi terputus ke server', 'error');
                closeCodeEditor();
            }
        }

        function closeCodeEditor() {
            document.getElementById('modalCodeEditor').style.display = 'none';
        }

        async function handleSaveCodeFile() {
            const textarea = document.getElementById('editorCodeTextarea');
            const btn = document.getElementById('btnSaveCodeFile');
            if (!textarea || !currentEditingPath) return;

            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span>Menyimpan...</span>';
            }

            try {
                const res = await fetch('api.php?action=save_file', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        path: currentEditingPath,
                        content: textarea.value
                    })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                } else {
                    showToast(data.error || 'Gagal menyimpan berkas', 'error');
                }
            } catch (err) {
                showToast('Koneksi terputus ke server', 'error');
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-floppy-fill"></i><span>Simpan Perubahan</span>';
                }
            }
        }

        // Tab Key & Ctrl+S Keyboard Shortcuts
        document.getElementById('editorCodeTextarea')?.addEventListener('keydown', function(e) {
            if (e.key === 'Tab') {
                e.preventDefault();
                const start = this.selectionStart;
                const end = this.selectionEnd;
                this.value = this.value.substring(0, start) + "    " + this.value.substring(end);
                this.selectionStart = this.selectionEnd = start + 4;
            }
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                handleSaveCodeFile();
            }
        });

        function editSelectedItem() {
            if (selectedItem && selectedItem.is_editable && !isCloudMode) {
                openCodeEditor(selectedItem.path);
            }
        }

        async function downloadSelectedItem() {
            if (!selectedItem) return;
            if (isCloudMode) {
                // Download from Cloud Remote to local
                const targetLocal = prompt("Simpan berkas cloud ke direktori lokal Orange Pi:", "/root/opi-dashboard");
                if (!targetLocal) return;

                showToast(`Mengunduh ${selectedItem.name} dari cloud...`, 'info');
                try {
                    const res = await fetch('api.php?action=download_from_rclone', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            remote_path: selectedItem.path,
                            local_dir: targetLocal
                        })
                    });
                    const json = await res.json();
                    if (json.success) {
                        showToast(json.message, 'success');
                    } else {
                        showToast(json.error || 'Gagal mengunduh', 'error');
                    }
                } catch (e) {
                    showToast('Gagal terhubung ke server', 'error');
                }
            } else if (!selectedItem.is_dir) {
                window.open(selectedItem.path, '_blank');
            }
        }

        async function promptRenameSelectedItem() {
            if (!selectedItem) return;
            const newName = prompt(`Ubah nama '${selectedItem.name}' menjadi:`, selectedItem.name);
            if (!newName || newName === selectedItem.name) return;

            try {
                const res = await fetch('api.php?action=rename_item', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        old_path: selectedItem.path,
                        new_name: newName
                    })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                    navigateToPath(currentPath);
                } else {
                    showToast(data.error || 'Gagal mengubah nama', 'error');
                }
            } catch (err) {
                showToast('Koneksi terputus ke server', 'error');
            }
        }

        async function deleteSelectedItem() {
            if (!selectedItem) return;
            if (!confirm(`Apakah Anda yakin ingin menghapus '${selectedItem.name}'?`)) return;

            try {
                const res = await fetch('api.php?action=delete_item', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ path: selectedItem.path })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                    selectedItem = null;
                    navigateToPath(currentPath);
                } else {
                    showToast(data.error || 'Gagal menghapus item', 'error');
                }
            } catch (err) {
                showToast('Koneksi terputus ke server', 'error');
            }
        }

        // --- Modals Handlers ---
        function openNewFileModal() {
            document.getElementById('modalNewFile').style.display = 'flex';
            document.getElementById('inputNewFileName').focus();
        }
        function closeNewFileModal() {
            document.getElementById('modalNewFile').style.display = 'none';
        }
        async function handleNewFileSubmit(e) {
            e.preventDefault();
            const fileName = document.getElementById('inputNewFileName').value.trim();
            const content = document.getElementById('inputNewFileContent').value;
            if (!fileName) return;

            try {
                const res = await fetch('api.php?action=create_file', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        dir: currentPath,
                        filename: fileName,
                        content: content
                    })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                    closeNewFileModal();
                    navigateToPath(currentPath);
                } else {
                    showToast(data.error || 'Gagal membuat berkas', 'error');
                }
            } catch (err) {
                showToast('Koneksi terputus ke server', 'error');
            }
        }

        function openNewFolderModal() {
            document.getElementById('modalNewFolder').style.display = 'flex';
            document.getElementById('inputNewFolderName').focus();
        }
        function closeNewFolderModal() {
            document.getElementById('modalNewFolder').style.display = 'none';
        }
        async function handleNewFolderSubmit(e) {
            e.preventDefault();
            const folderName = document.getElementById('inputNewFolderName').value.trim();
            if (!folderName) return;

            try {
                const res = await fetch('api.php?action=create_folder', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        dir: currentPath,
                        folder_name: folderName
                    })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                    closeNewFolderModal();
                    navigateToPath(currentPath);
                } else {
                    showToast(data.error || 'Gagal membuat folder', 'error');
                }
            } catch (err) {
                showToast('Koneksi terputus ke server', 'error');
            }
        }

        function openUploadModal() {
            document.getElementById('modalUploadFile').style.display = 'flex';
        }
        function closeUploadModal() {
            document.getElementById('modalUploadFile').style.display = 'none';
        }
        function handleFileSelected(input) {
            const label = document.getElementById('uploadFileNameLabel');
            if (input.files && input.files[0]) {
                label.textContent = `Dipilih: ${input.files[0].name} (${(input.files[0].size / 1024).toFixed(1)} KB)`;
            }
        }
        async function handleUploadSubmit(e) {
            e.preventDefault();
            const input = document.getElementById('fileUploadInput');
            const btn = document.getElementById('btnSubmitUpload');
            if (!input.files || !input.files[0]) {
                showToast('Silakan pilih berkas terlebih dahulu', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('dir', currentPath);
            formData.append('file', input.files[0]);

            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span>Mengunggah...</span>';
            }

            try {
                const res = await fetch('api.php?action=upload_file', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                    closeUploadModal();
                    navigateToPath(currentPath);
                } else {
                    showToast(data.error || 'Gagal mengunggah berkas', 'error');
                }
            } catch (err) {
                showToast('Koneksi terputus ke server', 'error');
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = 'Unggah Sekarang';
                }
            }
        }

        // =========================================================================
        // RCLONE MANAGER CONTROLLER
        // =========================================================================
        async function openRcloneManagerModal() {
            document.getElementById('modalRcloneManager').style.display = 'flex';
            switchRcloneTab('list');
            await loadRcloneStatus();
        }

        function closeRcloneManagerModal() {
            document.getElementById('modalRcloneManager').style.display = 'none';
        }

        function switchRcloneTab(tab) {
            document.getElementById('btnRcloneTabList')?.classList.toggle('active', tab === 'list');
            document.getElementById('btnRcloneTabLogin')?.classList.toggle('active', tab === 'login');
            document.getElementById('btnRcloneTabConfig')?.classList.toggle('active', tab === 'config');
            document.getElementById('tabRcloneList').style.display = (tab === 'list') ? 'flex' : 'none';
            document.getElementById('tabRcloneLogin').style.display = (tab === 'login') ? 'flex' : 'none';
            document.getElementById('tabRcloneConfig').style.display = (tab === 'config') ? 'flex' : 'none';
            if (tab === 'config') {
                loadRcloneConfigText();
            } else if (tab === 'login') {
                handleCloudProviderChange();
            }
        }

        function renderRcloneRemotesList(remotes) {
            const grid = document.getElementById('rcloneRemotesGrid');
            const empty = document.getElementById('rcloneEmptyRemotes');
            if (!grid) return;

            if (!remotes || remotes.length === 0) {
                grid.innerHTML = '';
                if (empty) empty.style.display = 'block';
                return;
            }

            if (empty) empty.style.display = 'none';
            let html = '';
            remotes.forEach(r => {
                html += `
                    <div class="cloud-remote-card">
                        <div style="display: flex; align-items: center; gap: 10px; overflow: hidden; flex: 1;" onclick="openRemoteFromModal('${escapeJs(r.remote)}')">
                            <div class="file-badge-icon" style="color: ${r.color}; width: 36px; height: 36px; font-size: 17px;">
                                <i class="bi ${r.icon}"></i>
                            </div>
                            <div style="overflow: hidden;">
                                <strong style="font-size: 12.5px; color: var(--text-heading); display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${escapeHtml(r.name)}</strong>
                                <span style="font-size: 10.5px; color: var(--text-muted); text-transform: uppercase;">${escapeHtml(r.type)}</span>
                            </div>
                        </div>
                        <div style="display: flex; gap: 6px;">
                            <button type="button" class="btn-primary-neumorphic" onclick="openRemoteFromModal('${escapeJs(r.remote)}')" style="padding: 6px 10px; font-size: 11px;" title="Buka Berkas Cloud">
                                <i class="bi bi-folder-symlink-fill"></i>
                            </button>
                            <button type="button" class="btn-new-device" onclick="testRemoteConnection('${escapeJs(r.name)}')" style="padding: 6px 8px; font-size: 11px; color: #0284c7;" title="Uji Koneksi (Test Ping)">
                                <i class="bi bi-lightning-charge-fill"></i>
                            </button>
                            <button type="button" class="btn-new-device" onclick="deleteRemoteAccount('${escapeJs(r.name)}')" style="padding: 6px 8px; font-size: 11px; color: #ef4444;" title="Hapus Akun Ini">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                `;
            });
            grid.innerHTML = html;
        }

        function openRemoteFromModal(remote) {
            closeRcloneManagerModal();
            navigateToPath(remote);
        }

        async function testRemoteConnection(remoteName) {
            showToast(`Menguji koneksi ke ${remoteName}...`, 'info');
            try {
                const res = await fetch(`api.php?action=test_rclone_remote&name=${encodeURIComponent(remoteName)}`);
                const json = await res.json();
                if (json.success) {
                    showToast(json.message, 'success');
                } else {
                    showToast(json.error || 'Koneksi gagal', 'error');
                }
            } catch (err) {
                showToast('Gagal menghubungi server', 'error');
            }
        }

        async function deleteRemoteAccount(remoteName) {
            if (!confirm(`Apakah Anda yakin ingin menghapus remote '${remoteName}' dari konfigurasi?`)) return;
            try {
                const res = await fetch('api.php?action=delete_rclone_remote', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name: remoteName })
                });
                const json = await res.json();
                if (json.success) {
                    showToast(json.message, 'success');
                    if (json.data && json.data.remotes) {
                        rcloneRemotes = json.data.remotes;
                        renderRcloneRemotesList(rcloneRemotes);
                    }
                } else {
                    showToast(json.error || 'Gagal menghapus remote', 'error');
                }
            } catch (err) {
                showToast('Gagal menghubungi server', 'error');
            }
        }

        function handleCloudProviderChange() {
            const type = document.getElementById('cloudLoginType')?.value || 'drive';
            const container = document.getElementById('cloudDynamicFields');
            if (!container) return;

            let fieldsHtml = '';
            if (type === 'drive') {
                fieldsHtml = `
                    <div>
                        <label style="font-size: 11px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 4px;">TOKEN OTORISASI / OAUTH JSON (DARI GOOGLE / RCLONE AUTHORIZE):</label>
                        <textarea id="opt_token" class="btn-new-device" rows="4" placeholder='{"access_token":"...","token_type":"Bearer","refresh_token":"...","expiry":"..."}' style="width: 100%; text-align: left; padding: 8px 12px; font-size: 11.5px; font-family: monospace;" required></textarea>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div>
                            <label style="font-size: 10.5px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 4px;">CLIENT ID (OPSIONAL):</label>
                            <input type="text" id="opt_client_id" class="btn-new-device" placeholder="Biarkan kosong jika default" style="width: 100%; text-align: left; padding: 8px 12px; font-size: 11.5px;">
                        </div>
                        <div>
                            <label style="font-size: 10.5px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 4px;">CLIENT SECRET (OPSIONAL):</label>
                            <input type="text" id="opt_client_secret" class="btn-new-device" placeholder="Biarkan kosong jika default" style="width: 100%; text-align: left; padding: 8px 12px; font-size: 11.5px;">
                        </div>
                    </div>
                    <input type="hidden" id="opt_scope" value="drive">
                `;
            } else if (type === 'onedrive') {
                fieldsHtml = `
                    <div>
                        <label style="font-size: 11px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 4px;">TOKEN MICROSOFT ONEDRIVE (OAUTH JSON):</label>
                        <textarea id="opt_token" class="btn-new-device" rows="4" placeholder='{"access_token":"...","token_type":"Bearer","refresh_token":"...","expiry":"..."}' style="width: 100%; text-align: left; padding: 8px 12px; font-size: 11.5px; font-family: monospace;" required></textarea>
                    </div>
                `;
            } else if (type === 'webdav') {
                fieldsHtml = `
                    <div>
                        <label style="font-size: 11px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 4px;">URL HOST WEBDAV / NEXTCLOUD:</label>
                        <input type="text" id="opt_url" class="btn-new-device" placeholder="https://cloud.example.com/remote.php/webdav/" style="width: 100%; text-align: left; padding: 8px 12px; font-size: 12px;" required>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div>
                            <label style="font-size: 10.5px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 4px;">USERNAME:</label>
                            <input type="text" id="opt_user" class="btn-new-device" placeholder="Username login" style="width: 100%; text-align: left; padding: 8px 12px; font-size: 12px;" required>
                        </div>
                        <div>
                            <label style="font-size: 10.5px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 4px;">PASSWORD / APP TOKEN:</label>
                            <input type="password" id="opt_pass" class="btn-new-device" placeholder="Password login" style="width: 100%; text-align: left; padding: 8px 12px; font-size: 12px;" required>
                        </div>
                    </div>
                    <input type="hidden" id="opt_vendor" value="nextcloud">
                `;
            } else if (type === 'mega') {
                fieldsHtml = `
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div>
                            <label style="font-size: 10.5px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 4px;">EMAIL AKUN MEGA:</label>
                            <input type="email" id="opt_user" class="btn-new-device" placeholder="user@domain.com" style="width: 100%; text-align: left; padding: 8px 12px; font-size: 12px;" required>
                        </div>
                        <div>
                            <label style="font-size: 10.5px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 4px;">PASSWORD:</label>
                            <input type="password" id="opt_pass" class="btn-new-device" placeholder="Password Mega" style="width: 100%; text-align: left; padding: 8px 12px; font-size: 12px;" required>
                        </div>
                    </div>
                `;
            } else if (type === 's3') {
                fieldsHtml = `
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div>
                            <label style="font-size: 10.5px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 4px;">ACCESS KEY ID:</label>
                            <input type="text" id="opt_access_key_id" class="btn-new-device" placeholder="AKIA..." style="width: 100%; text-align: left; padding: 8px 12px; font-size: 12px;" required>
                        </div>
                        <div>
                            <label style="font-size: 10.5px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 4px;">SECRET ACCESS KEY:</label>
                            <input type="password" id="opt_secret_access_key" class="btn-new-device" placeholder="Secret Key" style="width: 100%; text-align: left; padding: 8px 12px; font-size: 12px;" required>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div>
                            <label style="font-size: 10.5px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 4px;">REGION (CONTOH: ap-southeast-1):</label>
                            <input type="text" id="opt_region" class="btn-new-device" placeholder="ap-southeast-1" style="width: 100%; text-align: left; padding: 8px 12px; font-size: 12px;">
                        </div>
                        <div>
                            <label style="font-size: 10.5px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 4px;">ENDPOINT (OPSIONAL UNTUK R2/MINIO):</label>
                            <input type="text" id="opt_endpoint" class="btn-new-device" placeholder="https://<account>.r2.cloudflarestorage.com" style="width: 100%; text-align: left; padding: 8px 12px; font-size: 12px;">
                        </div>
                    </div>
                    <input type="hidden" id="opt_provider" value="AWS">
                `;
            } else if (type === 'dropbox') {
                fieldsHtml = `
                    <div>
                        <label style="font-size: 11px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 4px;">DROPBOX AUTH TOKEN:</label>
                        <textarea id="opt_token" class="btn-new-device" rows="3" placeholder="Paste token akses Dropbox..." style="width: 100%; text-align: left; padding: 8px 12px; font-size: 12px;" required></textarea>
                    </div>
                `;
            } else if (type === 'sftp' || type === 'ftp') {
                const defaultPort = (type === 'sftp') ? '22' : '21';
                fieldsHtml = `
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 10px;">
                        <div>
                            <label style="font-size: 10.5px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 4px;">HOST IP / DOMAIN:</label>
                            <input type="text" id="opt_host" class="btn-new-device" placeholder="contoh: 192.168.1.100 atau sftp.domain.com" style="width: 100%; text-align: left; padding: 8px 12px; font-size: 12px;" required>
                        </div>
                        <div>
                            <label style="font-size: 10.5px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 4px;">PORT:</label>
                            <input type="number" id="opt_port" class="btn-new-device" value="${defaultPort}" style="width: 100%; text-align: left; padding: 8px 12px; font-size: 12px;" required>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div>
                            <label style="font-size: 10.5px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 4px;">USERNAME:</label>
                            <input type="text" id="opt_user" class="btn-new-device" placeholder="root atau admin" style="width: 100%; text-align: left; padding: 8px 12px; font-size: 12px;" required>
                        </div>
                        <div>
                            <label style="font-size: 10.5px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 4px;">PASSWORD:</label>
                            <input type="password" id="opt_pass" class="btn-new-device" placeholder="Password login" style="width: 100%; text-align: left; padding: 8px 12px; font-size: 12px;" required>
                        </div>
                    </div>
                `;
            }

            container.innerHTML = fieldsHtml;
        }

        async function handleCloudLoginSubmit(e) {
            e.preventDefault();
            const name = document.getElementById('cloudLoginName')?.value.trim();
            const type = document.getElementById('cloudLoginType')?.value || 'drive';
            const btn = document.getElementById('btnSubmitCloudLogin');
            if (!name) return;

            const options = {};
            document.querySelectorAll('#cloudDynamicFields [id^="opt_"]').forEach(input => {
                const key = input.id.replace('opt_', '');
                options[key] = input.value.trim();
            });

            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span>Menghubungkan Akun...</span>';
            }

            showToast(`Mendaftarkan akun ${name}...`, 'info');

            try {
                const res = await fetch('api.php?action=create_rclone_remote', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        name: name,
                        type: type,
                        options: options
                    })
                });
                const json = await res.json();
                if (json.success) {
                    showToast(json.message, 'success');
                    if (json.data && json.data.remotes) {
                        rcloneRemotes = json.data.remotes;
                        renderRcloneRemotesList(rcloneRemotes);
                    }
                    switchRcloneTab('list');
                    // Reset form
                    document.getElementById('cloudLoginName').value = '';
                } else {
                    showToast(json.error || 'Gagal menghubungkan akun', 'error');
                }
            } catch (err) {
                showToast('Gagal menghubungi server', 'error');
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-shield-lock-fill"></i><span>Hubungkan & Simpan Akun</span>';
                }
            }
        }

        async function loadRcloneConfigText() {
            const ta = document.getElementById('rcloneConfigTextarea');
            if (!ta) return;
            ta.value = 'Memuat rclone.conf...';
            try {
                const res = await fetch('api.php?action=get_rclone_config');
                const json = await res.json();
                if (json.success) {
                    ta.value = json.config || '';
                }
            } catch (err) {
                showToast('Gagal memuat rclone.conf', 'error');
            }
        }

        async function handleSaveRcloneConfig() {
            const ta = document.getElementById('rcloneConfigTextarea');
            const btn = document.getElementById('btnSaveRcloneConfig');
            if (!ta) return;

            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span>Menyimpan...</span>';
            }

            try {
                const res = await fetch('api.php?action=save_rclone_config', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ config: ta.value })
                });
                const json = await res.json();
                if (json.success) {
                    showToast(json.message, 'success');
                    if (json.data && json.data.remotes) {
                        rcloneRemotes = json.data.remotes;
                        renderRcloneRemotesList(rcloneRemotes);
                    }
                } else {
                    showToast(json.error || 'Gagal menyimpan konfigurasi', 'error');
                }
            } catch (err) {
                showToast('Koneksi terputus ke server', 'error');
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-floppy-fill"></i><span>Simpan Konfigurasi</span>';
                }
            }
        }

        function insertSampleRcloneTemplate() {
            const ta = document.getElementById('rcloneConfigTextarea');
            if (!ta) return;
            const template = `[gdrive]
type = drive
scope = drive
token = {"access_token":"SAMPLE_TOKEN","token_type":"Bearer","refresh_token":"SAMPLE_REFRESH","expiry":"2026-12-31T00:00:00Z"}

[mywebdav]
type = webdav
url = https://webdav.example.com
vendor = other
user = admin
pass = samplepassword

[backup-s3]
type = s3
provider = AWS
access_key_id = AKIAEXAMPLE
secret_access_key = SECRETEXAMPLE
region = ap-southeast-1
`;
            ta.value = ta.value.trim() ? ta.value + "\n\n" + template : template;
            showToast('Contoh template rclone disisipkan ke editor!', 'info');
        }

        // --- Sync To Cloud Handlers ---
        function openSyncToCloudModal() {
            const src = selectedItem ? selectedItem.path : currentPath;
            document.getElementById('inputSyncLocalSource').value = src;
            const defaultRemote = (rcloneRemotes.length > 0) ? rcloneRemotes[0].remote + 'Backup/OrangePi' : 'gdrive:Backup/OrangePi';
            document.getElementById('inputSyncRemoteTarget').value = defaultRemote;
            document.getElementById('modalSyncCloud').style.display = 'flex';
        }

        function closeSyncToCloudModal() {
            document.getElementById('modalSyncCloud').style.display = 'none';
        }

        async function handleSyncCloudSubmit(e) {
            e.preventDefault();
            const local = document.getElementById('inputSyncLocalSource').value.trim();
            const remote = document.getElementById('inputSyncRemoteTarget').value.trim();
            const btn = document.getElementById('btnSubmitSyncCloud');

            if (!local || !remote) return;

            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span>Menyinkronkan...</span>';
            }
            showToast(`Memulai sinkronisasi ${local} ke ${remote}...`, 'info');

            try {
                const res = await fetch('api.php?action=sync_to_rclone', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        local_path: local,
                        remote_path: remote
                    })
                });
                const json = await res.json();
                if (json.success) {
                    showToast(json.message, 'success');
                    closeSyncToCloudModal();
                } else {
                    showToast(json.error || 'Gagal sinkronisasi', 'error');
                }
            } catch (err) {
                showToast('Koneksi terputus ke server', 'error');
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = 'Mulai Cadangkan';
                }
            }
        }

        function showToast(message, type = 'info') {
            const toastContainer = document.getElementById('toastContainer');
            if (!toastContainer) return;
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.innerHTML = `<span>${message}</span>`;
            toastContainer.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(10px)';
                toast.style.transition = '0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        function escapeHtml(str) {
            return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }
        function escapeJs(str) {
            return String(str || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
        }
    </script>
</body>
</html>
