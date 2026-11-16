window.setupCompanyProfileForm = function () {
    const form = document.getElementById('company-form');
    if (!form) {
        return;
    }
    const error = document.getElementById('company-image-error');
    const objectUrls = new Map();
    const limits = { logo: 2, cover: 5, office_images: 3 };
    let pendingImages = 0;

    function clearPreview(field) {
        (objectUrls.get(field) || []).forEach(url => URL.revokeObjectURL(url));
        objectUrls.set(field, []);
        document.getElementById('preview-' + field).replaceChildren();
    }

    function checkGalleryCount() {
        const existing = form.querySelectorAll('input[name="remove_office_images[]"]');
        const retained = [...existing].filter(input => !input.checked).length;
        const input = document.getElementById('company-office_images');
        input.setCustomValidity(retained + input.files.length > 6 ? 'Tổng ảnh văn phòng sau lưu tối đa 6 ảnh.' : '');
        return input.validity.valid;
    }

    function validateImage(file) {
        return new Promise(function (resolve, reject) {
            const url = URL.createObjectURL(file);
            const image = new Image();
            image.onload = function () {
                if (image.naturalWidth > 5000 || image.naturalHeight > 5000) {
                    URL.revokeObjectURL(url);
                    reject(new Error('Ảnh tối đa 5000 × 5000 pixel.'));
                } else {
                    resolve(url);
                }
            };
            image.onerror = function () {
                URL.revokeObjectURL(url);
                reject(new Error('Không đọc được ảnh. Hãy chọn JPG, PNG hoặc WebP hợp lệ.'));
            };
            image.src = url;
        });
    }

    form.querySelectorAll('[data-company-image]').forEach(function (input) {
        let selection = 0;
        input.addEventListener('change', async function () {
            const version = ++selection;
            const field = input.dataset.companyImage;
            const files = [...input.files];
            clearPreview(field);
            input.setCustomValidity('');
            error.hidden = true;
            pendingImages++;
            const urls = [];
            try {
                if (field === 'office_images' && !checkGalleryCount()) {
                    throw new Error('Tổng ảnh văn phòng sau lưu tối đa 6 ảnh.');
                }
                for (const file of files) {
                    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || !/\.(jpe?g|png|webp)$/i.test(file.name)) {
                        throw new Error('Chỉ chấp nhận tệp JPG, PNG và WebP.');
                    }
                    if (file.size > limits[field] * 1024 * 1024) {
                        throw new Error('Ảnh vượt giới hạn ' + limits[field] + ' MB.');
                    }
                    urls.push(await validateImage(file));
                }
                if (version !== selection) {
                    urls.forEach(url => URL.revokeObjectURL(url));
                    return;
                }
                objectUrls.set(field, urls);
                const preview = document.getElementById('preview-' + field);
                urls.forEach(function (url, index) {
                    const image = document.createElement('img');
                    image.src = url;
                    image.alt = 'Xem trước ' + files[index].name;
                    image.className = 'company-upload-preview';
                    preview.append(image);
                });
            } catch (failure) {
                urls.forEach(url => URL.revokeObjectURL(url));
                if (version === selection) {
                    input.value = '';
                    input.setCustomValidity(failure.message);
                    error.textContent = failure.message;
                    error.hidden = false;
                }
            } finally {
                pendingImages--;
            }
        });
    });
    form.querySelectorAll('input[name="remove_office_images[]"]').forEach(input => input.addEventListener('change', checkGalleryCount));
    form.addEventListener('input', function (event) {
        if (event.target.type !== 'file' && event.target.setCustomValidity) {
            event.target.setCustomValidity('');
        }
    });
    form.addEventListener('submit', function (event) {
        if (pendingImages) {
            event.preventDefault();
            error.textContent = 'Đang kiểm tra ảnh, vui lòng chờ rồi lưu lại.';
            error.hidden = false;
            return;
        }
        form.querySelectorAll('[required]').forEach(function (input) {
            input.setCustomValidity(input.value.trim() ? '' : 'Vui lòng nhập trường bắt buộc.');
        });
        checkGalleryCount();
        if (!form.reportValidity()) {
            event.preventDefault();
        }
    });
    window.addEventListener('pagehide', function () {
        objectUrls.forEach(urls => urls.forEach(url => URL.revokeObjectURL(url)));
    });
};
