<?php

/**
 * app/views/user/CustomizationView.php
 *
 * View file for the DashMed-SAE project.
 *
 * Notes:
 * - This docblock is intentionally file-scoped.
 * - Detailed PHPDoc for classes/methods is maintained near declarations.
 *
 * @package DashMed\SAE
 */

declare(strict_types=1);

namespace modules\views\user;

/**
 * Class CustomizationView
 *
 * View for dashboard customization page.
 *
 * @package DashMed\Modules\Views\Pages
 * @author  DashMed Team
 * @license Proprietary
 */
final class CustomizationView
{
    /**
     * Displays the customization page.
     *
     * @param  array<int, array{
     *   id: string,
     *   name: string,
     *   category: string,
     *   x: int,
     *   y: int,
     *   w: int,
     *   h: int
     * }> $widgets Active widgets
     * @param  array<int, array{id: string, name: string}>                                                                                                                                         $hidden         Hidden widgets
     * @param  array<int, array{parameter_id: string, display_name: string, category: string}>                                                                                                     $allParameters
     * @param  array<int, array{id: int, name: string, color: string, indicator_ids: array<int, string>}>                                                                                          $existingGroups
     * @param  array{group: array{id: int, name: string, color: string}, indicators: array<int, array{id: string, name: string, category: string, x: int|null, y: int|null, w: int, h: int}>}|null $editGroupData  Edit group data
     * @return void
     */
    public function show(
        array $widgets,
        array $hidden = [],
        array $allParameters = [],
        array $existingGroups = [],
        ?array $editGroupData = null
    ): void {
        $h = static function ($v) {
            return htmlspecialchars(is_scalar($v) ? (string)$v : '', ENT_QUOTES, 'UTF-8');
        };

        $layout = new \modules\views\layout\Layout(
            'Personnalisation',
            [
            'assets/css/pages/dashboard.css',
            'assets/css/components/searchbar/searchbar.css',
            'assets/css/pages/dashboard-customize.css',
            'assets/css/pages/customization.css',
            'https://cdn.jsdelivr.net/npm/gridstack@10/dist/gridstack.min.css',
            'assets/css/pages/medical-procedure.css',
            'assets/css/layout/aside/aside.css'
            ],
            [
            'https://cdn.jsdelivr.net/npm/gridstack@10/dist/gridstack-all.js',
            'assets/js/pages/customization-grid.js',
            'assets/js/pages/dash.js'
            ],
            '',
            true,
            true
        );

        $layout->render(
            function () use ($widgets, $hidden, $h, $allParameters, $existingGroups, $editGroupData) {
                ?>
                <?php
                $activeTab = 'layout';
                $rawTab = $_GET['tab'] ?? '';
                if (is_string($rawTab) && in_array($rawTab, ['layout', 'add_group', 'my_groups', 'edit_group'], true)) {
                    $activeTab = $rawTab;
                }

                /**
            * @var array{type: string, text: string}|null $groupMsg
            */
                $groupMsg = isset($_SESSION['group_msg']) && is_array($_SESSION['group_msg'])
                ? $_SESSION['group_msg']
                : null;
                unset($_SESSION['group_msg']);

                $groupsByCategory = [];
                foreach ($allParameters as $param) {
                    $cat = $param['category'];
                    $groupsByCategory[$cat][] = $param;
                }
                ksort($groupsByCategory);

                $existingGroupNames = array_map(
                    function ($g) {
                        return $g['name'];
                    },
                    $existingGroups
                );
                ?>

            <main class="container nav-space" id="customization-main">
                <section class="dashboard-content-container">
                    <?php include dirname(__DIR__) . '/partials/_searchbar.php'; ?>
                    <div class="skeleton-wrapper" id="skeleton-customization" data-skeleton-for="real-customization"
                        data-skeleton-auto data-skeleton-delay="400" style="width: 100%;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                            <div style="display: flex; flex-direction: column; gap: 6px;">
                                <div class="skeleton skeleton-text skeleton-text--xl" style="width: 300px;"></div>
                                <div class="skeleton skeleton-text" style="width: 280px;"></div>
                            </div>
                            <div style="display: flex; gap: 10px;">
                                <div class="skeleton skeleton-btn" style="width: 120px;"></div>
                                <div class="skeleton skeleton-btn" style="width: 120px;"></div>
                            </div>
                        </div>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem;">
                            <?php for ($cw = 0; $cw < 6; $cw++) : ?>
                                <div class="skeleton-widget">
                                    <div class="skeleton-widget-header">
                                        <div style="display: flex; flex-direction: column; gap: 4px;">
                                            <div class="skeleton skeleton-text" style="width: 120px; height: 14px;"></div>
                                            <div class="skeleton skeleton-text skeleton-text--sm" style="width: 80px;"></div>
                                        </div>
                                        <div style="display: flex; gap: 6px;">
                                            <div class="skeleton skeleton-circle" style="width: 24px; height: 24px;"></div>
                                            <div class="skeleton skeleton-circle" style="width: 24px; height: 24px;"></div>
                                        </div>
                                    </div>
                                    <div class="skeleton skeleton-widget-body"></div>
                                </div>
                                <?php
                            endfor; ?>
                        </div>
                    </div>

                    <div id="real-customization" style="display: none; width: 100%;">

                        <section class="dm-customize">
                            <div class="dm-customize-header">
                                <div>
                                    <h1>Personnaliser le tableau de bord</h1>
                                    <p>Déplacez, redimensionnez , masquez les widgets ou créez vos groupes.</p>
                                </div>
                                <div class="dm-customize-actions" id="dm-layout-actions" <?php echo $activeTab !== 'layout' ? 'style="display:none"' : ''?>>
                                    <button type="button" id="reset-layout-btn"
                                        class="dm-btn dm-btn--secondary">Réinitialiser</button>
                                    <button type="submit" form="customize-form" class="dm-btn dm-btn--primary">Enregistrer</button>
                                </div>
                            </div>

                            <div class="dm-tabs">
                                <button type="button" class="dm-tab-label <?php echo $activeTab === 'layout' ? 'active' : ''?>"
                                    data-target="tab-layout">Disposition principale</button>
                                <div class="category-vert-separator"></div>
                                <button type="button" class="dm-tab-label <?php echo $activeTab === 'my_groups' ? 'active' : ''?>"
                                    data-target="tab-my_groups">
                                    Mes
                                    groupes
                                    <?php echo !empty($existingGroups) ? ' <span class="dm-tab-count">' . count($existingGroups) . '</span>' : ''?>
                                </button>
                                <div class="category-vert-separator"></div>
                                <button type="button"
                                    class="dm-tab-label dm-tab-add <?php echo $activeTab === 'add_group' ? 'active' : ''?>"
                                    data-target="tab-add_group" style="color: var(--text-brand, #3b82f6);">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"
                                        class="tab-icon-plus">
                                        <line x1="12" y1="5" x2="12" y2="19"></line>
                                        <line x1="5" y1="12" x2="19" y2="12"></line>
                                    </svg>
                                    Ajouter un groupe
                                </button>
                                <?php if ($editGroupData !== null) : ?>
                                    <button type="button" class="dm-tab-label <?php echo $activeTab === 'edit_group' ? 'active' : ''?>"
                                        data-target="tab-edit_group" style="display:none;">
                                        Modifier le groupe
                                    </button>
                                    <?php
                                endif; ?>
                            </div>
                            <?php if ($groupMsg !== null) : ?>
                                <div class="dm-alert dm-alert--<?php echo $groupMsg['type'] === 'success' ? 'success' : 'error'?>">
                                    <?php echo $h($groupMsg['text'])?>
                                </div>
                                <?php
                            endif; ?>

                            <?php if (isset($_GET['success'])) : ?>
                                <div class="dm-alert dm-alert--success">Préférences enregistrées.</div>
                                <?php
                            endif; ?>
                            <div id="tab-layout" class="dm-tab-content" <?php echo $activeTab !== 'layout' ? 'style="display:none;"' : ''?>>
                                <?php if (!empty($hidden)) : ?>
                                    <details class="dm-hidden-list" open>
                                        <summary>Widgets masqués</summary>
                                        <div class="dm-hidden-list-items" id="hidden-widgets-list">
                                            <?php foreach ($hidden as $hw) : ?>
                                                <span class="dm-hidden-chip" data-widget-id="<?php echo $h($hw['id'])?>">
                                                    <?php echo $h($hw['name'])?>
                                                    <button type="button">+</button>
                                                </span>
                                                <?php
                                            endforeach; ?>
                                        </div>
                                    </details>
                                    <?php
                                else : ?>
                                    <details class="dm-hidden-list" style="display:none">
                                        <summary>Widgets masqués</summary>
                                        <div class="dm-hidden-list-items" id="hidden-widgets-list"></div>
                                    </details>
                                    <?php
                                endif; ?>
                                <form method="POST" action="/?page=customization" id="customize-form">
                                    <input type="hidden" name="layout_data" id="layout-data">
                                    <input type="hidden" name="reset_layout" id="reset-layout">
                                    <div class="grid-stack dm-grid">
                                        <?php foreach ($widgets as $w) : ?>
                                            <div class="grid-stack-item" gs-x="<?php echo (string)(int)$w['x']?>" gs-y="<?php echo (string)(int)$w['y']?>"
                                                gs-w="<?php echo (string)max(4, (int)$w['w'])?>" gs-h="<?php echo (string)max(3, (int)$w['h'])?>" gs-min-w="4"
                                                gs-min-h="3" gs-id="<?php echo $h($w['id'])?>" data-widget-id="<?php echo $h($w['id'])?>">
                                                <div class="grid-stack-item-content">
                                                    <div class="dm-widget">
                                                        <div class="dm-widget-header">
                                                            <div>
                                                                <div class="dm-widget-title">
                                                                    <?php echo $h($w['name'])?>
                                                                </div>
                                                                <div class="dm-widget-category">
                                                                    <?php echo $h($w['category'])?>
                                                                </div>
                                                            </div>
                                                            <div class="dm-widget-controls">
                                                                <span class="dm-widget-grip" title="Déplacer"><svg viewBox="0
                                                                0 24 24" fill="none" stroke="currentColor"
                                                                        stroke-width="2">
                                                                        <circle cx="9" cy="5" r="1" />
                                                                        <circle cx="15" cy="5" r="1" />
                                                                        <circle cx="9" cy="12" r="1" />
                                                                        <circle cx="15" cy="12" r="1" />
                                                                        <circle cx="9" cy="19" r="1" />
                                                                        <circle cx="15" cy="19" r="1" />
                                                                    </svg></span>
                                                                <button type="button" class="dm-widget-hide" title="Masquer">
                                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                                        stroke-width="2">
                                                                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7
                                                                    0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9
                                                                    4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5
                                                                    18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1
                                                                    1-4.24-4.24" />
                                                                        <line x1="1" y1="1" x2="23" y2="23" />
                                                                    </svg></button>
                                                            </div>
                                                        </div>
                                                        <div class="dm-widget-body">
                                                            <div class="dm-widget-value">—</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php
                                        endforeach; ?>
                                    </div>
                                </form>
                            </div>

                            <div id="tab-add_group" class="dm-tab-content" <?php echo $activeTab !== 'add_group' ? 'style="display:none;"' : ''?>>
                                <div class="dm-layout-section">
                                    <label>Disposition au sein du nouveau groupe</label>
                                    <p>Réorganisez l'affichage des widgets pour ce groupe en les déplaçant ci-dessous.</p>
                                    <div class="grid-stack dm-grid" id="add-group-grid"></div>
                                </div>
                            </div>

                            <div id="tab-my_groups" class="dm-tab-content" <?php echo $activeTab !== 'my_groups' ? 'style="display:none;"' : ''?>>
                                <div class="dm-groups-list-wrap">
                                    <?php if (empty($existingGroups)) : ?>
                                        <p class="dm-no-groups">Aucun groupe personnalisé créé.</p>
                                        <?php
                                    else : ?>
                                        <table class="dm-groups-table">
                                            <thead>
                                                <tr>
                                                    <th>Nom</th>
                                                    <th>Indicateurs</th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($existingGroups as $group) : ?>
                                                    <tr>
                                                        <td>
                                                            <?php echo $h($group['name'])?>
                                                        </td>
                                                        <td>
                                                            <?php $count = count($group['indicator_ids']); ?>
                                                            <?php echo (string)$count?> indicateur<?php echo $count > 1 ? 's' : ''?>
                                                        </td>
                                                        <td>
                                                            <div style="display:flex; gap:10px; justify-content: flex-end;">
                                                                <a href="/?page=customization&tab=edit_group&id=<?php echo (string)(int)$group['id']?>"
                                                                    class="btn-icon edit-btn">
                                                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                                                                        stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                                        stroke-linejoin="round">
                                                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2
                                                                    2 0 0 0 2-2v-7"></path>
                                                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4
                                                                    1 1-4 9.5-9.5z"></path>
                                                                    </svg>
                                                                </a>
                                                                <form method="POST" action="/?page=custom_group"
                                                                    onsubmit="return confirm('Supprimer le groupe « <?php echo $h($group['name'])?> » ?')">
                                                                    <input type="hidden" name="action" value="delete_group">
                                                                    <input type="hidden" name="group_id" value="<?php echo (string)(int)$group['id']?>">
                                                                    <button type="submit" class="btn-icon delete-btn">
                                                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                                                                            stroke="#ef4444" stroke-width="2" stroke-linecap="round"
                                                                            stroke-linejoin="round">
                                                                            <polyline points="3 6 5 6 21 6"></polyline>
                                                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3
                                                                                0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2">
                                                                            </path>
                                                                        </svg>
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    <?php
                                                endforeach; ?>
                                            </tbody>
                                        </table>
                                        <?php
                                    endif; ?>
                                </div>
                            </div>
                            <?php if ($editGroupData !== null) :
                                $eg = $editGroupData['group'];
                                $egInds = $editGroupData['indicators'];
                                ?>
                                <div id="tab-edit_group" class="dm-tab-content" <?php echo $activeTab !== 'edit_group' ? 'style="display:none;"' : ''?>>



                                    <div class="dm-layout-section">
                                        <label>Disposition au sein du groupe</label>
                                        <p>Réorganisez l'affichage des widgets pour ce groupe en les déplaçant ci-dessous.</p>



                                        <div class="grid-stack dm-grid" id="edit-group-grid">
                                            <?php foreach ($egInds as $w) : ?>
                                                <div class="grid-stack-item group-grid-item" gs-x="<?php echo (string)(int)$w['x']?>"
                                                    gs-y="<?php echo (string)(int)$w['y']?>" gs-w="<?php echo (string)max(4, (int)$w['w'])?>"
                                                    gs-h="<?php echo (string)max(3, (int)$w['h'])?>" gs-min-w="4" gs-min-h="3"
                                                    gs-id="<?php echo $h($w['id'])?>" data-widget-id="<?php echo $h($w['id'])?>">
                                                    <div class="grid-stack-item-content">
                                                        <div class="dm-widget">
                                                            <div class="dm-widget-header">
                                                                <div>
                                                                    <div class="dm-widget-title">
                                                                        <?php echo $h($w['name'])?>
                                                                    </div>
                                                                    <div class="dm-widget-category">
                                                                        <?php echo $h($w['category'])?>
                                                                    </div>
                                                                </div>
                                                                <div class="dm-widget-controls">
                                                                    <span class="dm-widget-grip" title="Déplacer">
                                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                                            stroke-width="2">
                                                                            <circle cx="9" cy="5" r="1" />
                                                                            <circle cx="15" cy="5" r="1" />
                                                                            <circle cx="9" cy="12" r="1" />
                                                                            <circle cx="15" cy="12" r="1" />
                                                                            <circle cx="9" cy="19" r="1" />
                                                                            <circle cx="15" cy="19" r="1" />
                                                                        </svg>
                                                                    </span>

                                                                    <button type="button" class="dm-widget-remove" title="Supprimer">
                                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                                            stroke-width="2">
                                                                            <line x1="18" y1="6" x2="6" y2="18"></line>
                                                                            <line x1="6" y1="6" x2="18" y2="18"></line>
                                                                        </svg>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                            <div class="dm-widget-body">
                                                                <div class="dm-widget-value">—</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php
                                            endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                                <script>
                                    window.preloadedEditIndicators = <?php echo json_encode($egInds)?>;
                                </script>
                                <?php
                            endif; ?>
                        </section>

                    </div>
                </section>

                <button id="aside-restore-btn" onclick="toggleDesktopAside()" title="Afficher / Masquer le menu">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M15 18l-6-6 6-6" />
                    </svg>
                </button>
                <button id="aside-show-btn" onclick="toggleAside()">☰</button>
                <aside id="aside">
                    <div id="aside-add-group" style="display:none;">
                        <div class="dm-group-form-wrap" style="padding:15px; margin:0; width:100%;">
                            <form method="POST" action="/?page=custom_group" id="create-group-form" class="dm-form-card" novalidate>
                                <input type="hidden" name="action" value="create_group">
                                <input type="hidden" name="layout_data" id="add-layout-data">
                                <h2>Créer un nouveau groupe</h2>

                                <div class="dm-form-group">
                                    <label for="group_name">Nom du groupe</label>
                                    <div class="dm-input-wrap">
                                        <svg class="dm-input-icon" viewBox="0 0 24 24">
                                            <path stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <input type="text" id="group_name" name="group_name" maxlength="100" autocomplete="off"
                                            placeholder="Ex : Cardio avancé" required>
                                    </div>
                                    <span class="dm-field-error" id="name-error"></span>
                                </div>

                                <div class="dm-form-group">
                                    <label for="group_color">Couleur de l'onglet</label>
                                    <div class="dm-color-picker-wrap">
                                        <span class="dm-color-swatch" id="color-swatch-create" style="background:#2563eb;"></span>
                                        <input type="color" id="group_color" name="group_color" value="#2563eb"
                                            class="dm-color-input-hidden">
                                        <span class="dm-color-label" id="color-label-create">#2563eb</span>
                                    </div>
                                </div>

                                <div class="dm-form-group">
                                    <label>Indicateurs du groupe</label>
                                    <div class="dm-hidden-list" style="margin-bottom: 15px;">
                                        <div class="dm-hidden-list-title">Indicateurs sélectionnés</div>
                                        <div class="dm-hidden-list-items" id="selected-indicators-list">
                                            <p class="dm-no-groups" id="no-indicators-msg"
                                                style="margin: 0; padding: 5px 0; font-size: 0.85rem;">Aucun indicateur
                                                sélectionné.</p>
                                        </div>
                                    </div>
                                    <label>Indicateurs disponibles</label>
                                    <div class="dm-indicators-library">
                                        <?php foreach ($groupsByCategory as $cat => $params) : ?>
                                            <div class="dm-indicator-category">
                                                <span class="dm-indicator-cat-label">
                                                    <?php echo $h($cat)?>
                                                </span>
                                                <div class="dm-hidden-list-items" style="margin-top: 8px;">
                                                    <?php foreach ($params as $param) : ?>
                                                        <span class="dm-hidden-chip indicator-available-chip" data-cat="<?php echo $h($cat)?>"
                                                            data-id="<?php echo $h($param['parameter_id'])?>"
                                                            data-name="<?php echo $h($param['display_name'])?>">
                                                            <?php echo $h($param['display_name'])?>
                                                            <button type="button" class="add-indicator-btn">+</button>
                                                        </span>
                                                        <?php
                                                    endforeach; ?>
                                                </div>
                                            </div>
                                            <?php
                                        endforeach; ?>
                                    </div>
                                    <span class="dm-field-error" id="indicators-error"></span>
                                    <div id="hidden-inputs-container"></div>
                                </div>

                                <button type="submit" class="dm-submit-btn" id="create-group-btn">Créer le groupe</button>
                            </form>
                        </div>
                    </div>
                    <?php if ($editGroupData !== null) : ?>
                        <div id="aside-edit-group" style="display:none;">
                            <div class="dm-group-form-wrap" style="padding:15px; margin:0; width:100%;">
                                <form method="POST" action="/?page=custom_group" id="edit-group-form" class="dm-form-card" novalidate>
                                    <input type="hidden" name="action" value="edit_group">
                                    <input type="hidden" name="group_id" value="<?php echo $h($eg['id'])?>">
                                    <input type="hidden" name="layout_data" id="edit-layout-data">
                                    <h2>Modifier
                                        <?php echo $h($eg['name'])?>
                                    </h2>

                                    <div class="dm-form-group">
                                        <label for="edit_group_name">Nom du groupe</label>
                                        <div class="dm-input-wrap">
                                            <svg class="dm-input-icon" viewBox="0 0 24 24">
                                                <path stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                            <input type="text" id="edit_group_name" name="group_name" maxlength="100" autocomplete="off"
                                                value="<?php echo $h($eg['name'])?>" required>
                                        </div>
                                        <span class="dm-field-error" id="edit-name-error"></span>
                                    </div>

                                    <div class="dm-form-group">
                                        <label for="edit_group_color">Couleur de l'onglet</label>
                                        <div class="dm-color-picker-wrap">
                                            <span class="dm-color-swatch" id="color-swatch-edit"
                                                style="background:<?php echo $h($eg['color'])?>;"></span>
                                            <input type="color" id="edit_group_color" name="group_color" value="<?php echo $h($eg['color'])?>"
                                                class="dm-color-input-hidden">
                                            <span class="dm-color-label" id="color-label-edit">
                                                <?php echo $h($eg['color'])?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="dm-form-group">
                                        <label>Indicateurs du groupe</label>
                                        <div class="dm-hidden-list" style="margin-bottom: 15px;">
                                            <div class="dm-hidden-list-title">Indicateurs sélectionnés</div>
                                            <div class="dm-hidden-list-items" id="edit-selected-indicators-list">
                                                <p class="dm-no-groups" id="edit-no-indicators-msg"
                                                    style="margin: 0; padding: 5px 0; font-size: 0.85rem;">Aucun indicateur
                                                    sélectionné.</p>
                                            </div>
                                        </div>
                                        <label>Indicateurs disponibles</label>
                                        <div class="dm-indicators-library">
                                            <?php foreach ($groupsByCategory as $cat => $params) : ?>
                                                <div class="dm-indicator-category">
                                                    <span class="dm-indicator-cat-label">
                                                        <?php echo $h($cat)?>
                                                    </span>
                                                    <div class="dm-hidden-list-items" style="margin-top: 8px;">
                                                        <?php foreach ($params as $param) : ?>
                                                            <span class="dm-hidden-chip edit-indicator-available-chip"
                                                                data-id="<?php echo $h($param['parameter_id'])?>"
                                                                data-name="<?php echo $h($param['display_name'])?>" data-cat="<?php echo $h($cat)?>">
                                                                <?php echo $h($param['display_name'])?>
                                                                <button type="button" class="edit-add-indicator-btn">+</button>
                                                            </span>
                                                            <?php
                                                        endforeach; ?>
                                                    </div>
                                                </div>
                                                <?php
                                            endforeach; ?>
                                        </div>
                                        <span class="dm-field-error" id="edit-indicators-error"></span>
                                        <div id="edit-hidden-inputs-container"></div>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <?php
                    endif; ?>
                </aside>

            </main>

            <div id="unsaved-bar" class="unsaved-bar" style="display:none;">
                <p>Modifications non enregistrées</p>
                <button type="submit" id="save-changes-btn" class="dm-btn dm-btn--primary">Enregistrer</button>
            </div>

            <script>
                (function () {
                    const tabLabels = document.querySelectorAll('.dm-tab-label');
                    const tabContents = document.querySelectorAll('.dm-tab-content');
                    const layoutActions = document.getElementById('dm-layout-actions');

                    tabLabels.forEach(label => {
                        label.addEventListener('click', () => {
                            if (label.tagName !== 'BUTTON') return;

                            tabLabels.forEach(l => l.classList.remove('active'));
                            tabContents.forEach(c => c.style.display = 'none');

                            label.classList.add('active');
                            const targetId = label.getAttribute('data-target');
                            const targetContent = document.getElementById(targetId);
                            if (targetContent) {
                                targetContent.style.display = 'block';
                            }

                            const unsavedBar = document.getElementById('unsaved-bar');
                            if (targetId === 'tab-layout') {
                                layoutActions.style.display = 'flex';
                            } else {
                                layoutActions.style.display = 'none';
                            }

                            if (unsavedBar) {
                                unsavedBar.style.display = 'none';
                            }

                            const mainContainer = document.getElementById('customization-main');
                            const aside = document.getElementById('aside');
                            const asideAdd = document.getElementById('aside-add-group');
                            const asideEdit = document.getElementById('aside-edit-group');
                            const asideShowBtn = document.getElementById('aside-show-btn');
                            const asideRestoreBtn = document.getElementById('aside-restore-btn');

                            if (mainContainer && aside) {
                                if (targetId === 'tab-add_group' || targetId === 'tab-edit_group') {
                                    mainContainer.classList.add('aside-space');
                                    aside.style.display = '';
                                    if (asideRestoreBtn) asideRestoreBtn.style.display = '';
                                    if (asideShowBtn) asideShowBtn.style.display = '';

                                    if (asideAdd) asideAdd.style.display = targetId === 'tab-add_group' ? 'block' : 'none';
                                    if (asideEdit) asideEdit.style.display = targetId === 'tab-edit_group' ? 'block' : 'none';

                                    setTimeout(() => window.dispatchEvent(new Event('resize')), 50);
                                } else {
                                    mainContainer.classList.remove('aside-space');
                                    aside.style.display = 'none';
                                    if (asideRestoreBtn) asideRestoreBtn.style.display = 'none';
                                    if (asideShowBtn) asideShowBtn.style.display = 'none';
                                }
                            }
                        });
                    });

                    const urlParams = new URLSearchParams(window.location.search);
                    const tabParam = urlParams.get('tab');
                    if (tabParam) {
                        const targetLabel = document.querySelector(`.dm-tab-label[data-target="tab-${tabParam}"]`);
                        if (targetLabel) targetLabel.click();
                    } else {
                        const activeLabel = document.querySelector('.dm-tab-label.active');
                        if (activeLabel) activeLabel.click();
                    }

                    document.getElementById('save-changes-btn')?.addEventListener('click', (e) => {
                        e.preventDefault();
                        const editGroupTab = document.getElementById('tab-edit_group');
                        const isEditGroupActive = editGroupTab && editGroupTab.style.display !== 'none';
                        const addGroupTab = document.getElementById('tab-add_group');
                        const isAddGroupActive = addGroupTab && addGroupTab.style.display !== 'none';

                        if (isEditGroupActive) {
                            const editForm = document.getElementById('edit-group-form');
                            if (window._editGridManager) window._editGridManager.updateLayout();
                            if (editForm) editForm.requestSubmit();
                        } else if (isAddGroupActive) {
                            const addForm = document.getElementById('create-group-form');
                            if (window._addGridManager) window._addGridManager.updateLayout();
                            if (addForm) addForm.requestSubmit();
                        } else {
                            if (window._mainGridManager) window._mainGridManager.updateLayout();
                            document.getElementById('customize-form')?.submit();
                        }
                    });

                    const existingNames = <?php echo json_encode(array_values($existingGroupNames))?>;
                    const form = document.getElementById('create-group-form');
                    const nameInput = document.getElementById('group_name');
                    const nameError = document.getElementById('name-error');
                    const indicatorsError = document.getElementById('indicators-error');

                    const availableChips = document.querySelectorAll('.indicator-available-chip');
                    const selectedList = document.getElementById('selected-indicators-list');
                    const noIndicatorsMsg = document.getElementById('no-indicators-msg');
                    const hiddenInputsContainer = document.getElementById('hidden-inputs-container');

                    let selectedIndicators = new Map();

                    window.removeIndicatorFromAddGroup = function (removeId) {
                        if (!selectedIndicators.has(removeId)) return;
                        selectedIndicators.delete(removeId);

                        const available = document.querySelector(`.indicator-available-chip[data-id="${removeId}"]`);
                        if (available) {
                            available.style.opacity = '1';
                            available.style.pointerEvents = 'auto';
                            available.querySelector('.add-indicator-btn').style.display = 'inline-block';
                        }
                        updateSelectedIndicators();
                    };

                    availableChips.forEach(chip => {
                        const btn = chip.querySelector('.add-indicator-btn');
                        btn.addEventListener('click', (e) => {
                            e.preventDefault();
                            const id = chip.getAttribute('data-id');
                            const name = chip.getAttribute('data-name');
                            const cat = chip.getAttribute('data-cat');

                            if (selectedIndicators.has(id)) return;

                            selectedIndicators.set(id, { name, cat });
                            chip.style.opacity = '0.5';
                            chip.style.pointerEvents = 'none';
                            chip.querySelector('.add-indicator-btn').style.display = 'none';

                            updateSelectedIndicators(id);
                        });
                    });

                    function updateSelectedIndicators(addedId = null) {
                        if (selectedIndicators.size === 0) {
                            noIndicatorsMsg.style.display = 'block';
                        } else {
                            noIndicatorsMsg.style.display = 'none';
                        }

                        const chips = selectedList.querySelectorAll('.dm-hidden-chip');
                        chips.forEach(c => c.remove());
                        hiddenInputsContainer.innerHTML = '';

                        selectedIndicators.forEach((data, id) => {
                            const chip = document.createElement('span');
                            chip.className = 'dm-hidden-chip';
                            chip.innerHTML = `${data.name} <button type="button" class="remove-indicator-btn" data-id="${id}">×</button>`;
                            selectedList.appendChild(chip);

                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'indicators[]';
                            input.value = id;
                            hiddenInputsContainer.appendChild(input);

                            chip.querySelector('.remove-indicator-btn').addEventListener('click', (e) => {
                                e.preventDefault();
                                const removeId = e.target.getAttribute('data-id');
                                window.removeIndicatorFromAddGroup(removeId);

                                if (window._addGridManager) {
                                    const addGridEl = document.getElementById('add-group-grid');
                                    if (addGridEl) {
                                        const gridItem = addGridEl.querySelector(`.group-grid-item[data-widget-id="${removeId}"]`);
                                        if (gridItem) window._addGridManager.grid.removeWidget(gridItem);
                                    }
                                }
                            });

                            if (addedId === id && window._addGridManager && window.DmGrid) {
                                const el = window._addGridManager.addWidget({
                                    w: 4, h: 3, minW: 4, minH: 3,
                                    content: window.DmGrid.createWidgetContent(data.name, data.cat, true)
                                });
                                if (el) {
                                    el.dataset.widgetId = id;
                                    el.classList.add('group-grid-item');
                                }
                            }
                        });
                    }

                    function collectGroupLayout(gridEl) {
                        if (!gridEl) return [];
                        const items = [];
                        gridEl.querySelectorAll('.group-grid-item').forEach(el => {
                            const id = el.dataset.widgetId || el.getAttribute('data-widget-id') || el.getAttribute('gs-id');
                            if (!id) return;

                            const x = Number(el.getAttribute('gs-x') ?? el.getAttribute('data-gs-x') ?? el.dataset.gsX ?? 0);
                            const y = Number(el.getAttribute('gs-y') ?? el.getAttribute('data-gs-y') ?? el.dataset.gsY ?? 0);
                            const w = Number(el.getAttribute('gs-w') ?? el.getAttribute('data-gs-w') ?? el.dataset.gsW ?? 4);
                            const h = Number(el.getAttribute('gs-h') ?? el.getAttribute('data-gs-h') ?? el.dataset.gsH ?? 3);

                            items.push({ id, x, y, w, h, visible: true });
                        });
                        return items;
                    }

                    form.addEventListener('submit', function (e) {
                        let valid = true;
                        nameError.textContent = '';
                        indicatorsError.textContent = '';

                        const name = nameInput.value.trim();
                        if (!name) {
                            nameError.textContent = 'Le nom est obligatoire.';
                            if (valid) nameInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            valid = false;
                        } else if (existingNames.map(n => n.toLowerCase()).includes(name.toLowerCase())) {
                            nameError.textContent = 'Ce nom est déjà utilisé.';
                            if (valid) nameInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            valid = false;
                        }

                        if (selectedIndicators.size === 0) {
                            indicatorsError.textContent = 'Sélectionnez au moins un indicateur.';
                            if (valid) indicatorsError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            valid = false;
                        }

                        if (window._addGridManager) {
                            window._addGridManager.updateLayout();
                        }

                        const addGridEl = document.getElementById('add-group-grid');
                        const layoutInput = document.getElementById('add-layout-data');
                        if (layoutInput) {
                            layoutInput.value = JSON.stringify(collectGroupLayout(addGridEl));
                        }

                        if (!valid) e.preventDefault();
                    });

                    const editForm = document.getElementById('edit-group-form');
                    if (editForm) {
                        const editGridManager = window._editGridManager || null;
                        const editGridEl = document.getElementById('edit-group-grid');

                        const editAvailableChips = document.querySelectorAll('.edit-indicator-available-chip');
                        const editSelectedList = document.getElementById('edit-selected-indicators-list');
                        const editNoIndicatorsMsg = document.getElementById('edit-no-indicators-msg');
                        const editHiddenInputsContainer = document.getElementById('edit-hidden-inputs-container');

                        let editSelectedIndicators = new Map();

                        window.removeIndicatorFromGroup = function (removeId) {
                            if (!editSelectedIndicators.has(removeId)) return;
                            editSelectedIndicators.delete(removeId);

                            const available = document.querySelector(`.edit-indicator-available-chip[data-id="${removeId}"]`);
                            if (available) {
                                available.style.opacity = '1';
                                available.style.pointerEvents = 'auto';
                                available.querySelector('.edit-add-indicator-btn').style.display = 'inline-block';
                            }
                            updateEditSelectedIndicators();
                        };

                        function updateEditSelectedIndicators(isPreloading = false, addedId = null) {
                            editNoIndicatorsMsg.style.display = editSelectedIndicators.size === 0 ? 'block' : 'none';

                            if (!isPreloading) {
                                const unsavedBar = document.getElementById('unsaved-bar');
                                if (unsavedBar) unsavedBar.style.display = 'flex';
                            }

                            const chips = editSelectedList.querySelectorAll('.dm-hidden-chip');
                            chips.forEach(c => c.remove());
                            editHiddenInputsContainer.innerHTML = '';

                            editSelectedIndicators.forEach((data, id) => {
                                const chip = document.createElement('span');
                                chip.className = 'dm-hidden-chip';
                                chip.innerHTML = `${data.name} <button type="button" class="remove-indicator-btn" data-id="${id}">×</button>`;
                                editSelectedList.appendChild(chip);

                                const input = document.createElement('input');
                                input.type = 'hidden';
                                input.name = 'indicators[]';
                                input.value = id;
                                editHiddenInputsContainer.appendChild(input);

                                chip.querySelector('.remove-indicator-btn').addEventListener('click', (e) => {
                                    e.preventDefault();
                                    const removeId = e.target.getAttribute('data-id');
                                    window.removeIndicatorFromGroup(removeId);

                                    if (window._editGridManager) {
                                        const editGridEl = document.getElementById('edit-group-grid');
                                        if (editGridEl) {
                                            const gridItem = editGridEl.querySelector(`.group-grid-item[data-widget-id="${removeId}"]`);
                                            if (gridItem) window._editGridManager.grid.removeWidget(gridItem);
                                        }
                                    }
                                });

                                if (!isPreloading && addedId === id && window._editGridManager && window.DmGrid) {
                                    const el = window._editGridManager.addWidget({
                                        w: 4, h: 3, minW: 4, minH: 3,
                                        content: window.DmGrid.createWidgetContent(data.name, data.cat, true)
                                    });
                                    if (el) {
                                        el.dataset.widgetId = id;
                                        el.classList.add('group-grid-item');
                                    }
                                }
                            });
                        }

                        if (window.preloadedEditIndicators) {
                            window.preloadedEditIndicators.forEach(w => {
                                editSelectedIndicators.set(w.id, { name: w.name, cat: w.category });
                                const chip = document.querySelector(`.edit-indicator-available-chip[data-id="${w.id}"]`);
                                if (chip) {
                                    chip.style.opacity = '0.5';
                                    chip.style.pointerEvents = 'none';
                                    chip.querySelector('.edit-add-indicator-btn').style.display = 'none';
                                }
                            });
                            updateEditSelectedIndicators(true);
                        }

                        editAvailableChips.forEach(chip => {
                            const btn = chip.querySelector('.edit-add-indicator-btn');
                            btn.addEventListener('click', (e) => {
                                e.preventDefault();
                                const id = chip.getAttribute('data-id');
                                const name = chip.getAttribute('data-name');
                                const cat = chip.getAttribute('data-cat');

                                if (editSelectedIndicators.has(id)) return;

                                editSelectedIndicators.set(id, { name, cat });
                                chip.style.opacity = '0.5';
                                chip.style.pointerEvents = 'none';
                                btn.style.display = 'none';

                                updateEditSelectedIndicators(false, id);
                            });
                        });

                        const editFormInputs = [document.getElementById('edit_group_name'), document.getElementById('edit_group_color')];
                        editFormInputs.forEach(input => {
                            if (input) {
                                input.addEventListener('input', () => {
                                    const unsavedBar = document.getElementById('unsaved-bar');
                                    if (unsavedBar) unsavedBar.style.display = 'flex';
                                });
                            }
                        });

                        editForm.addEventListener('submit', function (e) {
                            let valid = true;
                            const nameErr = document.getElementById('edit-name-error');
                            const indErr = document.getElementById('edit-indicators-error');
                            nameErr.textContent = '';
                            indErr.textContent = '';

                            if (!document.getElementById('edit_group_name').value.trim()) {
                                nameErr.textContent = 'Le nom est obligatoire.';
                                if (valid) document.getElementById('edit_group_name').scrollIntoView({ behavior: 'smooth', block: 'center' });
                                valid = false;
                            }

                            if (editSelectedIndicators.size === 0) {
                                indErr.textContent = 'Sélectionnez au moins un indicateur.';
                                if (valid) document.getElementById('edit-indicators-error')?.scrollIntoView({ behavior: 'smooth', block: 'center' }) || indErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                valid = false;
                            }

                            if (editGridManager) {
                                editGridManager.updateLayout();
                            }

                            const editLayoutInput = document.getElementById('edit-layout-data');
                            if (editLayoutInput && editGridEl) {
                                editLayoutInput.value = JSON.stringify(collectGroupLayout(editGridEl));
                            }

                            if (!valid) e.preventDefault();
                        });
                    }
                })();
            </script>

            <script>
                (function () {
                    function bindColorSwatch(swatchId, inputId, labelId) {
                        const swatch = document.getElementById(swatchId);
                        const input = document.getElementById(inputId);
                        const label = document.getElementById(labelId);
                        if (!swatch || !input) return;

                        swatch.addEventListener('click', function () {
                            input.click();
                        });

                        input.addEventListener('input', function () {
                            swatch.style.background = input.value;
                            if (label) label.textContent = input.value;
                        });
                    }

                    bindColorSwatch('color-swatch-create', 'group_color', 'color-label-create');
                    bindColorSwatch('color-swatch-edit', 'edit_group_color', 'color-label-edit');
                })();
            </script>

                <?php
            }
        );
    }
}