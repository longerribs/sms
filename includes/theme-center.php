<!-- Theme Center Modal Component -->
<div id="themeCenter" class="theme-center-modal" style="display: none;">
    <div class="theme-center-backdrop" onclick="closeThemeCenter()"></div>
    <div class="theme-center-panel">
        <div class="theme-center-header">
            <h2><i class="fas fa-palette"></i> Theme & Accessibility Center</h2>
            <button class="theme-center-close" onclick="closeThemeCenter()" title="Close Modal">&times;</button>
        </div>
        
        <div class="theme-center-content">
            <!-- Mode Switcher & System Theme -->
            <section class="theme-section">
                <div style="display: flex; justify-content: space-between; align-items: center; background: var(--card-bg); padding: 1rem; border-radius: 12px; border: 1px solid var(--border-light); margin-bottom: 1.5rem;">
                    <div>
                        <div style="font-weight: 600; color: var(--text-primary); display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-desktop"></i> System Auto-Theme
                        </div>
                        <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.2rem;">Match system light/dark mode preference</div>
                    </div>
                    <label class="switch-toggle" style="position: relative; display: inline-block; width: 44px; height: 24px;">
                        <input type="checkbox" id="systemThemeToggle" onchange="toggleSystemTheme(this.checked)" style="opacity: 0; width: 0; height: 0;">
                        <span class="switch-slider"></span>
                    </label>
                </div>

                <h3><i class="fas fa-swatchbook"></i> Preset Themes</h3>
                <div id="themesContainer" class="themes-grid">
                    <!-- Populated dynamically by JS -->
                </div>
            </section>
            
            <!-- Color Customizer -->
            <section class="theme-section" style="margin-top: 2rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <h3 style="margin: 0;"><i class="fas fa-sliders-h"></i> Color Palette Customizer</h3>
                    <button class="btn-secondary" style="font-size: 0.75rem; padding: 0.3rem 0.6rem;" onclick="resetCustomizerColors()">
                        <i class="fas fa-undo"></i> Reset Preset Colors
                    </button>
                </div>
                <div id="colorCustomizer" class="color-customizer">
                    <!-- Populated by JS -->
                </div>
            </section>
            
            <!-- Live Preview Section -->
            <section class="theme-section" style="margin-top: 2rem;">
                <h3><i class="fas fa-eye"></i> Contrast & Readability Live Preview</h3>
                <div class="theme-preview-box">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <div style="font-weight: 700; font-size: 1.1rem; color: var(--text-primary);">Preview Dashboard Card</div>
                        <span style="font-size: 0.8rem; padding: 0.2rem 0.6rem; border-radius: 20px; background: rgba(99, 102, 241, 0.15); color: var(--primary); font-weight: 600;">Active Theme</span>
                    </div>
                    
                    <p style="color: var(--text-primary); margin-bottom: 0.4rem; font-weight: 500;">Primary Header Text (High Contrast)</p>
                    <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1rem;">Secondary / Muted descriptive body text for readability across themes.</p>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 0.75rem; margin-bottom: 1.25rem;">
                        <div style="background: var(--card-bg); border: 1px solid var(--border-light); padding: 0.75rem; border-radius: 10px;">
                            <div style="font-size: 0.75rem; color: var(--text-muted);">Units Remaining</div>
                            <div style="font-size: 1.3rem; font-weight: 700; color: var(--text-number);">1,250.00</div>
                        </div>
                        <div style="background: var(--card-bg); border: 1px solid var(--border-light); padding: 0.75rem; border-radius: 10px;">
                            <div style="font-size: 0.75rem; color: var(--text-muted);">DLR Delivery</div>
                            <div style="font-size: 1.3rem; font-weight: 700; color: var(--accent-success);">99.4%</div>
                        </div>
                    </div>

                    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center;">
                        <button class="btn-primary" style="font-size: 0.85rem; padding: 0.4rem 0.8rem;">Primary Button</button>
                        <button class="btn-secondary" style="font-size: 0.85rem; padding: 0.4rem 0.8rem;">Secondary Button</button>
                        <span style="background: rgba(16, 185, 129, 0.15); color: var(--accent-success); border: 1px solid var(--accent-success); padding: 0.25rem 0.6rem; border-radius: 6px; font-size: 0.75rem; font-weight: 600;">Success</span>
                        <span style="background: rgba(245, 158, 11, 0.15); color: var(--accent-warning); border: 1px solid var(--accent-warning); padding: 0.25rem 0.6rem; border-radius: 6px; font-size: 0.75rem; font-weight: 600;">Warning</span>
                        <span style="background: rgba(239, 68, 68, 0.15); color: var(--accent-error); border: 1px solid var(--accent-error); padding: 0.25rem 0.6rem; border-radius: 6px; font-size: 0.75rem; font-weight: 600;">Error</span>
                    </div>
                </div>
            </section>
        </div>
        
        <div class="theme-center-footer">
            <button onclick="resetTheme()" class="btn-secondary" style="margin-right: auto;">
                <i class="fas fa-redo"></i> Reset to Default
            </button>
            <button onclick="saveCustomTheme()" class="btn-primary">
                <i class="fas fa-save"></i> Save Custom Palette
            </button>
            <button onclick="closeThemeCenter()" class="btn-secondary">Close</button>
        </div>
    </div>
</div>

<!-- Theme Switcher Floating Action Button -->
<button id="themeSwitcherBtn" class="theme-switcher-btn" onclick="openThemeCenter()" title="Open Theme Center">
    <i class="fas fa-palette"></i>
</button>

<style>
    /* Switch toggle */
    .switch-slider {
        position: absolute;
        cursor: pointer;
        top: 0; left: 0; right: 0; bottom: 0;
        background-color: var(--border);
        transition: .3s;
        border-radius: 24px;
    }
    .switch-slider:before {
        position: absolute;
        content: "";
        height: 18px; width: 18px;
        left: 3px; bottom: 3px;
        background-color: white;
        transition: .3s;
        border-radius: 50%;
    }
    input:checked + .switch-slider {
        background-color: var(--primary);
    }
    input:checked + .switch-slider:before {
        transform: translateX(20px);
    }

    /* Floating Theme Switcher Button */
    .theme-switcher-btn {
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        width: 52px;
        height: 52px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        border: 2px solid var(--glass-border);
        color: white;
        font-size: 1.25rem;
        cursor: pointer;
        z-index: 999;
        box-shadow: 0 8px 24px var(--primary-glow);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .theme-switcher-btn:hover {
        transform: scale(1.1) rotate(15deg);
        box-shadow: 0 12px 30px var(--primary-glow);
    }
    
    .theme-center-modal {
        position: fixed;
        top: 0; left: 0; width: 100%; height: 100%;
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }
    
    .theme-center-backdrop {
        position: absolute;
        top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        z-index: -1;
    }
    
    .theme-center-panel {
        background: var(--bg-main);
        border: 1px solid var(--glass-border);
        border-radius: 20px;
        width: 100%;
        max-width: 680px;
        max-height: 85vh;
        overflow: hidden;
        z-index: 10001;
        display: flex;
        flex-direction: column;
        box-shadow: 0 20px 50px rgba(0,0,0,0.5);
    }
    
    .theme-center-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid var(--divider);
        background: var(--card-bg);
    }
    
    .theme-center-header h2 {
        margin: 0;
        font-size: 1.2rem;
        font-family: var(--font-display);
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .theme-center-close {
        background: none;
        border: none;
        color: var(--text-muted);
        font-size: 1.8rem;
        cursor: pointer;
        padding: 0;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }
    
    .theme-center-close:hover {
        color: var(--accent-error);
        background: rgba(239, 68, 68, 0.1);
    }
    
    .theme-center-content {
        flex: 1;
        padding: 1.5rem;
        overflow-y: auto;
    }
    
    .theme-section h3 {
        font-size: 1rem;
        margin-bottom: 1rem;
        color: var(--text-primary);
        font-family: var(--font-display);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .themes-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
        gap: 0.75rem;
    }
    
    .theme-card {
        padding: 0.85rem 0.75rem;
        border: 2px solid var(--border-light);
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.25s ease;
        text-align: center;
        background: var(--card-bg);
        position: relative;
    }
    
    .theme-card:hover {
        border-color: var(--primary);
        transform: translateY(-2px);
        background: var(--card-hover);
    }
    
    .theme-card.active {
        border-color: var(--primary);
        background: rgba(99, 102, 241, 0.12);
        box-shadow: 0 0 15px var(--primary-glow);
    }
    
    .theme-card-name {
        font-weight: 600;
        font-size: 0.88rem;
        color: var(--text-primary);
        margin-bottom: 0.25rem;
    }
    
    .theme-card-desc {
        font-size: 0.72rem;
        color: var(--text-muted);
        line-height: 1.2;
    }
    
    .color-customizer {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
        gap: 0.85rem;
    }
    
    .color-input-group {
        display: flex;
        flex-direction: column;
        gap: 0.4rem;
        background: var(--card-bg);
        padding: 0.75rem;
        border-radius: 10px;
        border: 1px solid var(--border-light);
    }
    
    .color-input-group label {
        font-size: 0.8rem;
        font-weight: 500;
        color: var(--text-muted);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .color-picker-row {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .color-picker-row input[type="color"] {
        width: 36px;
        height: 32px;
        border: 1px solid var(--border-light);
        border-radius: 6px;
        cursor: pointer;
        background: none;
        padding: 0;
    }
    
    .color-picker-row input[type="text"] {
        flex: 1;
        background: var(--bg-main);
        border: 1px solid var(--border-light);
        color: var(--text-primary);
        padding: 0.35rem 0.5rem;
        border-radius: 6px;
        font-family: monospace;
        font-size: 0.85rem;
    }
    
    .theme-preview-box {
        padding: 1.25rem;
        background: var(--card-bg);
        border-radius: 14px;
        border: 1px solid var(--border-light);
        box-shadow: inset 0 0 20px rgba(0,0,0,0.1);
    }
    
    .theme-center-footer {
        padding: 1rem 1.5rem;
        border-top: 1px solid var(--divider);
        background: var(--card-bg);
        display: flex;
        gap: 0.75rem;
        justify-content: flex-end;
        align-items: center;
    }
</style>
