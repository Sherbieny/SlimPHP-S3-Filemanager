class FileManager {
    constructor() {
        // Initialize any variables or state here
        this.sortingData = {};
        this.baseURL = window.location.origin;
        this.saveBtn = document.querySelector('#save-btn');
        this.foldersHtml = document.querySelector('#folder-list-container');
        this.filesHtml = document.querySelector('#file-list-container');
        this.uploadFileModalElement = document.querySelector('#upload-file-modal');
        this.createFolderModalElement = document.querySelector('#create-folder-modal');
        this.deleteFolderModalElement = document.querySelector('#delete-folder-modal');
        this.uploadFileModal = new bootstrap.Modal(this.uploadFileModalElement);
        this.createFolderModal = new bootstrap.Modal(this.createFolderModalElement);
        this.deleteFolderModal = new bootstrap.Modal(this.deleteFolderModalElement);
        this.deleteFolderBtn = document.querySelector('#delete-folder-confirm-btn');
        this.mainLoadingSpinner = document.querySelector('#main-loading-spinner');
        this.sidebarLoadingSpinner = document.querySelector('#sidebar-loading-spinner');

        this.currentFolder = '';

        this.createEvents();

    }

    initSortable = () => {
        const sortableContainers = document.querySelectorAll('#files-list');
        sortableContainers.forEach((container) => {
            new Sortable(container, {
                animation: 150,
                onEnd: (evt) => {
                    //console.log(evt);
                    // This event is triggered when the user stops dragging an item
                    // evt.item: the element that was moved
                    // evt.newIndex: the new index of the item
                    // evt.oldIndex: the old index of the item
                    // all indexes are 0-based so you will need to add 1 to get the actual sort_order
                    // You can use this event to update the sort_order of the images
                    this.saveBtn.classList.remove('d-none');
                    this.updateSortOrder(evt.item, evt.newIndex, evt.oldIndex);
                },
            });
        });
    }

    // Method to create events for user clicks and other actions
    createEvents() {
        // Add event listeners for the buttons
        // create folder modal open event
        this.createFolderModalElement.addEventListener('show.bs.modal', this.handleCreateFolderModalOpen);
        this.createFolderModalElement.querySelector('#create-folder-form').addEventListener('submit', this.createFolder);
        // upload file modal open event
        this.uploadFileModalElement.addEventListener('show.bs.modal', this.handleUploadFileModalOpen);
        // upload file form submit event
        this.uploadFileModalElement.querySelector('#upload-file-form').addEventListener('submit', this.uploadFile);
        // delete folder modal open event
        this.deleteFolderModalElement.addEventListener('show.bs.modal', this.handleDeleteFolderModalOpen);
        // delete folder button click event
        this.deleteFolderBtn.addEventListener('click', this.handleDeleteFolder);


        // save button click event
        this.saveBtn.addEventListener('click', this.setSortOrder);
        // create folder button click event
        this.addFolderEvents();
    }

    updateSortOrder = (item, newIndex, oldIndex) => {
        const dataPath = item.dataset.path || item.querySelector('[data-path]')?.dataset.path;

        if (!dataPath) {
            return;
        }

        // advance the indexes by 1
        newIndex++;
        oldIndex++;

        const filesInFolder = Array.from(document.querySelectorAll('#files-list .sortable-elem'));

        // Update the sorting data
        this.sortingData = {};
        filesInFolder.forEach((file, index) => {
            this.sortingData[file.dataset.path] = index + 1;
        });
    }

    showMainLoadingSpinner = () => {
        this.mainLoadingSpinner.classList.remove('d-none');
    }

    hideMainLoadingSpinner = () => {
        this.mainLoadingSpinner.classList.add('d-none');
    }

    showSidebarLoadingSpinner = () => {
        this.sidebarLoadingSpinner.classList.remove('d-none');
    }

    hideSidebarLoadingSpinner = () => {
        this.sidebarLoadingSpinner.classList.add('d-none');
    }

    showBtnSpinner = (btn) => {
        btn.disabled = true;
        btn.querySelector('.spinner-grow').classList.remove('d-none');
    }

    hideBtnSpinner = (btn) => {
        btn.disabled = false;
        btn.querySelector('.spinner-grow').classList.add('d-none');
    }

    //APIs

    /**
     * Method to get all folders in the bucket
     * Api: GET /loadFolders
     * @param {boolean} force - Force to get the folders from the server
     */
    getFolders = async () => {
        // Add logic to get all folders
        try {
            this.showSidebarLoadingSpinner();

            const response = await fetch(`${this.baseURL}/loadFolders`);
            let data = await response.text();

            if (data) {
                this.foldersHtml.innerHTML = data;
                this.addFolderEvents();
            }

            this.hideSidebarLoadingSpinner();

        } catch (error) {
            console.log(error);
            this.hideSidebarLoadingSpinner();
        }
    }

    /**
     * Method to get all files in a folder
     * Api: GET /loadFiles
     */
    getFiles = async (path) => {
        // Add logic to get all files
        try {
            this.showMainLoadingSpinner();
            const response = await fetch(`${this.baseURL}/loadFiles?path=${path}`);
            const data = await response.text();
            if (data) {
                this.filesHtml.innerHTML = data;
                this.initSortable();
                this.addDeleteBtnEvents();
            }

            this.hideMainLoadingSpinner();
        } catch (error) {
            console.log(error);
            this.hideMainLoadingSpinner();
        }
    }

    /**
     * Method to create a folder
     * Api: POST /createFolder
     */
    createFolder = async (event) => {
        event.preventDefault();
        const btn = event.target.querySelector('button[type="submit"]');
        this.showBtnSpinner(btn);

        // Add logic to create a folder
        let folderName = document.querySelector('#folder-name').value;
        if (!folderName) {
            alert('Folder name is required');
            return;
        }
        let path = this.currentFolder || '/';

        let folder = {
            path: path.endsWith('/') ? path + folderName : path + '/' + folderName
        };
        try {
            const response = await fetch(`${this.baseURL}/createFolder`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(folder)
            });

            await response.json();


            this.hideBtnSpinner(btn);
            this.createFolderModal.hide();
            this.getFolders(true);
        } catch (error) {
            console.log(error);
        }
    }

    /**
     * Method to upload a file
     * Api: POST /uploadFile
     */
    uploadFile = async (event) => {
        event.preventDefault();
        const btn = event.target.querySelector('button[type="submit"]');
        this.showBtnSpinner(btn);

        // Add logic to upload a file
        let fileInput = document.querySelector('#file');
        let file = fileInput.files[0];
        let formData = new FormData();
        formData.append('file', file);
        formData.append('path', this.currentFolder || '/');
        try {
            const response = await fetch(`${this.baseURL}/uploadFile`, {
                method: 'POST',
                body: formData
            });

            await response.json();

            this.hideBtnSpinner(btn);
            this.uploadFileModal.hide();
            this.getFiles(this.currentFolder);
        } catch (error) {
            console.log(error);
        }
    }

    /**
     * Method to delete a file
     * Api: DELETE /deleteFile
     */
    deleteFile = async (path) => {
        // Add logic to delete a file
        try {
            const response = await fetch(`${this.baseURL}/deleteFile`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ path })
            });

            await response.json();
            await this.getFolders(true);
        } catch (error) {
            console.log(error);
        }
    }

    /**
     * Method to delete a folder
     * Api: DELETE /deleteFolder
     */
    deleteFolder = async (path) => {
        // Add logic to delete a folder
        try {
            const response = await fetch(`${this.baseURL}/deleteFolder`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ path })
            });

            await response.json();
            await this.getFolders(true);
        } catch (error) {
            console.log(error);
        }
    }


    /**
     * Method to update the sort order of the files
     * Api: POST /sort_order
     */
    setSortOrder = async (event) => {
        const btn = event.target;
        this.showBtnSpinner(btn);

        const formData = new FormData();
        formData.append('sortingData', JSON.stringify(this.sortingData));
        try {
            const response = await fetch(`${this.baseURL}/sortOrder`, {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            this.hideBtnSpinner(btn);
            this.saveBtn.classList.add('d-none');

            if (!data.success) {
                alert('Failed to update sort order');
                console.log(data);

                return;
            }

            alert('Sort order updated successfully');

        } catch (e) {
            console.log(e);
            this.hideBtnSpinner(btn);
        }

    }

    // Method to add events to the folders leaf nodes
    addFolderEvents = () => {
        let folderElements = document.querySelectorAll('.folder');
        folderElements.forEach(element => {
            element.addEventListener('click', this.handleFolderClick);
            element.addEventListener('dblclick', this.handleFolderDblClick);
        });
    }

    // Method to add events to the delete buttons
    addDeleteBtnEvents = () => {
        document.querySelectorAll('.delete-file-btn').forEach(btn => {
            btn.addEventListener('click', this.handleDeleteFile);
        });
    }

    handleFolderDblClick = (event) => {
        event.stopPropagation();
        let element = event.target;
        if (typeof element !== 'Button') {
            element = element.closest('.folder');
            if (!element) {
                return;
            }
        }

        let path = element.dataset.path;
        this.currentFolder = path;
        if (!path) {
            return;
        }
        this.getFiles(path);
    }

    handleFolderClick = (event) => {
        event.stopPropagation();
        event.target.classList.add('active');
        document.querySelectorAll('.folder').forEach(node => {
            if (node !== event.target) {
                node.classList.remove('active');
            } else {
                this.currentFolder = node.dataset.path;
            }
        });
    }

    handleDeleteFile = async (event) => {
        event.stopPropagation();
        let element = event.target;
        if (element.tagName !== 'I') {
            element = element.querySelector('i');
        }
        let path = element.closest('.file').dataset.path;
        if (!path) {
            return;
        }

        if (!confirm('Are you sure you want to delete this file?')) {
            return;
        }

        this.showMainLoadingSpinner();

        await this.deleteFile(path);

        this.hideMainLoadingSpinner();

        element.closest('.file').remove();
    }

    handleDeleteFolder = async (event) => {
        event.stopPropagation();
        if (!this.currentFolder) {
            return;
        }

        const btn = event.target;
        this.showBtnSpinner(btn);

        await this.deleteFolder(this.currentFolder);

        this.currentFolder = '';
        this.hideBtnSpinner(btn);
        this.deleteFolderModal.hide();
    }


    handleCreateFolderModalOpen = () => {
        const message = `Enter the name of the folder you want to create in the current folder: ${this.currentFolder || '/'}`;
        document.querySelector('#create-folder-message').textContent = message;
    }

    handleUploadFileModalOpen = () => {
        const message = `Upload a file to the current folder: ${this.currentFolder || '/'}`;
        document.querySelector('#upload-file-message').textContent = message;
    }

    handleDeleteFolderModalOpen = () => {
        const message = `${this.currentFolder || '/'}`;
        document.querySelector('#delete-folder-message').textContent = message;
    }
}


document.addEventListener('DOMContentLoaded', () => {
    new FileManager();
});
