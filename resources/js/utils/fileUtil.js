import * as axios from 'axios';

const BASE_URL = window.location.origin;

function fileUpload(formData) {
    const url = `${BASE_URL}/api/upload-file`;
    return axios.post(url, formData);
}

function fileConvert(formData) {
    const url = `${BASE_URL}/api/convert-file`;
    return axios.post(url, formData);
}

function jobCheck(formData) {
    const url = `${BASE_URL}/api/check-job`;
    return axios.post(url, formData);
}

function fileDownload(formData) {
    const url = `${BASE_URL}/api/download-file`;
    return axios.post(url, formData);
}

// function htmlFileSplit(formData) {
//     const url = `${BASE_URL}/api/split-html-file`;
//     return axios.post(url, formData);
// }

// function htmlTranslate(formData) {
//     const url = `${BASE_URL}/api/translate-html`;
//     return axios.post(url, formData);
// }

// function htmlsMerge(formData) {
//     const url = `${BASE_URL}/api/merge-htmls`;
//     return axios.post(url, formData);
// }

// function htmlPdfConvert(formData) {
//     const url = `${BASE_URL}/api/convert-html-pdf`;
//     return axios.get(url);
// }

export { 
    fileUpload, 
    fileConvert, 
    jobCheck, 
    fileDownload, 
    // htmlFileSplit, 
    // htmlPdfConvert, 
    // htmlTranslate, 
    // htmlsMerge 
}