const fs = require('fs-extra');

async function copyTinyMCE() {
    try {
        const tinymceSrcDir = './node_modules/tinymce';
        const tinymceDestDir = './public/plugins/tinymce';
        await fs.ensureDir(tinymceDestDir);
        await fs.copy(tinymceSrcDir, tinymceDestDir, { overwrite: true });
    } catch (err) {
        console.error('Error copying TinyMCE files:', err);
    }
}

copyTinyMCE();