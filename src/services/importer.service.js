import { Observable, Subject } from 'rxjs';

const AJAX_BASE = window.iwp.ajax_base;
let service_xhr = {};

const importerSubject = new Subject(null);
const settingsSubject = new Subject(null);
const compatibilitySubject = new Subject(null);

export const importer = {
  get,
  save,
  remove,
  status,
  importers,
  upload,
  filePreview,
  recordPreview,
  process,
  getAndSubscribe,
  run,
  init,
  logs,
  log,
  fieldOptions,
  pause,
  stop,
  check,
  migrate,
  abort,
  // Settings
  getSettings,
  saveSettings,
  getCompatibility,
  saveCompatibility,
  debug_log,
  template,
  templateUniqueIdentifiers,
  toolExport,
  toolImport,
};

function abort(id = null) {
  if (id !== null) {
    service_xhr['_' + id] = service_xhr.hasOwnProperty('_' + id)
      ? service_xhr['_' + id] + 1
      : 0;

    if (service_xhr.hasOwnProperty(id)) {
      const xhr = service_xhr[id];
      if (xhr !== null) {
        xhr.abort();
        delete service_xhr[id];
      }
    }

    return getAbortToken(id);
  }

  const service_keys = Object.keys(service_xhr).filter(
    (service) => !service.startsWith('_')
  );
  service_keys.forEach((service) => abort(service));
  return true;
}

function getAbortToken(id) {
  return { id: '_' + id, counter: service_xhr['_' + id] };
}

function aborted(token) {
  if (token.counter === service_xhr[token.id]) {
    return false;
  }
  return true;
}

function init(id) {
  const abortToken = abort('init');

  return new Promise((resolve, reject) => {
    service_xhr.init = window.jQuery.ajax({
      url: AJAX_BASE + '/importer/' + id + '/init',
      dataType: 'json',
      method: 'GET',
      beforeSend: function (xhr) {
        xhr.setRequestHeader('X-WP-Nonce', window.iwp.nonce);
      },
      success: function (response) {
        if (response.status === 'S') {
          resolve(response.data);
        } else {
          reject(response.data);
        }
      },
      error: function (response) {
        if (!aborted(abortToken)) {
          reject(response.statusText);
        }
      },
    });
  });
}

function run(id, session) {
  let abort = false;
  let xhr_requests = [];

  const newConnection = (subscriber) => {
    let jsonResponse = '',
      lastResponseLen = false;

    const xhr_request = window.jQuery.ajax({
      url: AJAX_BASE + '/importer/' + id + '/run',
      dataType: 'text',
      method: 'POST',
      data: {
        session: session,
      },
      beforeSend: function (xhr) {
        xhr.setRequestHeader('X-WP-Nonce', window.iwp.nonce);
      },
      // xhrFields: {
      //   onprogress: function (e) {
      //     var thisResponse,
      //       response = e.currentTarget.response;
      //     if (lastResponseLen === false) {
      //       thisResponse = response;
      //       lastResponseLen = 0;
      //     } else {
      //       thisResponse = response.substring(lastResponseLen);
      //     }

      //     // TODO: Loop through response until \n, add that length onto lastResponseLen, repeat for all new line characters
      //     const split_string = '\n';
      //     while (thisResponse.includes(split_string)) {
      //       const pos = thisResponse.indexOf(split_string);
      //       const part = thisResponse.substring(0, pos);

      //       subscriber.next(JSON.parse(part));

      //       lastResponseLen += pos + split_string.length;
      //       thisResponse = thisResponse.substring(pos + 1);
      //     }
      //   },
      // },
      success: function (data) {
        try {
          const startChar = data.substring(0, 1);
          if (startChar !== '{') {
            // we have some broken data?
            const pos = data.indexOf('{');
            if (pos > -1) {
              data = data.substring(pos);
            }
          }

          if (data.trim().length > 0) {
            subscriber.next(JSON.parse(data));
          } else {
            // no data came back
            // subscriber.error('Empty Response');
          }
        } catch (e) {
          // console.log('Error Parsing data: ' + data);
          // subscriber.error('Error Parsing data: ' + data);
        }
      },
      complete: function () {
        if (!abort) {
          setTimeout(() => {
            newConnection(subscriber);
          }, 300);
        }
      },
      error: function (response) {
        if (response.status === 200 && response.statusText === 'OK') {
          return;
        }
        subscriber.error(response);
      },
    });

    xhr_requests.push(xhr_request);
  };

  return {
    abort: function () {
      abort = true;
      while (xhr_requests.length) {
        const xhr_request = xhr_requests.shift();
        if (xhr_request !== null) {
          xhr_request.abort();
        }
      }
    },
    request: new Observable((subscriber) => {
      abort = false;
      newConnection(subscriber);
    }),
  };
}

function getAndSubscribe(id) {
  get(id);
  return importerSubject;
}

function get(id) {
  const abortToken = abort('get');

  return new Promise((resolve, reject) => {
    service_xhr.get = window.jQuery.ajax({
      url: AJAX_BASE + '/importer/' + id,
      dataType: 'json',
      method: 'GET',
      beforeSend: function (xhr) {
        xhr.setRequestHeader('X-WP-Nonce', window.iwp.nonce);
      },
      success: function (response) {
        // if aborted skip
        if (aborted(abortToken)) {
          return;
        }

        if (response.status === 'S') {
          importerSubject.next(response.data);
          resolve(response.data);
        } else {
          importerSubject.error(response.data);
          reject(response.data);
        }
      },
      error: function (response) {
        // if aborted skip
        if (aborted(abortToken)) {
          return;
        }

        importerSubject.error(response);
        reject(response.statusText);
      },
    });
  });
}

function process(id, data = {}) {
  let _xhr;

  return {
    abort: () => {
      if (_xhr !== null) {
        _xhr.abort();
      }
    },
    promise: new Promise((resolve, reject) => {
      _xhr = window.jQuery.ajax({
        url: AJAX_BASE + '/importer/' + id + '/file-process',
        dataType: 'json',
        method: 'POST',
        data: data,
        beforeSend: (xhr) => {
          xhr.setRequestHeader('X-WP-Nonce', window.iwp.nonce);
        },
        success: (response) => {
          if (response.status === 'S') {
            importerSubject.next(response.data);
            resolve(response.data);
          } else {
            reject(response.data);
          }
        },
        error: (response) => {
          reject(response.statusText);
        },
      });
    }),
  };
}

// function process(id, data = {}) {
//   abort();

//   return new Promise((resolve, reject) => {
//     current_xhr = window.jQuery.ajax({
//       url: AJAX_BASE + '/importer/' + id + '/file-process',
//       dataType: 'json',
//       method: 'POST',
//       data: data,
//       beforeSend: function (xhr) {
//         xhr.setRequestHeader('X-WP-Nonce', window.iwp.nonce);
//       },
//       success: function (response) {
//         if (response.status === 'S') {
//           resolve(response.data);
//         } else {
//           reject(response.data);
//         }
//       },
//       error: function (response) {
//         reject(response.statusText);
//       }
//     });
//   });
// }

function filePreview(id, data = {}) {
  const abortToken = abort('filePreview');

  return new Promise((resolve, reject) => {
    service_xhr.filePreview = window.jQuery.ajax({
      url: AJAX_BASE + '/importer/' + id + '/file-preview',
      dataType: 'json',
      method: 'POST',
      data: data,
      beforeSend: function (xhr) {
        xhr.setRequestHeader('X-WP-Nonce', window.iwp.nonce);
      },
      success: function (response) {
        if (response.status === 'S') {
          resolve(response.data);
        } else {
          reject(response.data);
        }
      },
      error: function (response) {
        if (!aborted(abortToken)) {
          reject(response.statusText);
        }
      },
    });
  });
}

function recordPreview(id, fields = {}) {
  const abortToken = abort('recordPreview' + Object.keys(fields).join('-'));

  return new Promise((resolve, reject) => {
    service_xhr.recordPreview = window.jQuery.ajax({
      url: AJAX_BASE + '/importer/' + id + '/preview',
      dataType: 'json',
      method: 'POST',
      data: fields,
      beforeSend: function (xhr) {
        xhr.setRequestHeader('X-WP-Nonce', window.iwp.nonce);
      },
      success: function (response) {
        if (response.status === 'S') {
          resolve(response.data);
        } else {
          reject(response.data);
        }
      },
      error: function (response) {
        if (!aborted(abortToken)) {
          reject(response.statusText);
        }
      },
    });
  });
}

function upload(id, form_data) {
  const abortToken = abort('upload');

  return new Promise((resolve, reject) => {
    const url = AJAX_BASE + '/importer/' + id + '/upload';
    service_xhr.upload = window.jQuery.ajax({
      url: url,
      dataType: 'json',
      contentType: false,
      processData: false,
      method: 'POST',
      data: form_data,
      beforeSend: function (xhr) {
        xhr.setRequestHeader('X-WP-Nonce', window.iwp.nonce);
      },
      success: function (response) {
        if (response.status === 'S') {
          importerSubject.next(response.data);
          resolve(response.data);
        } else {
          reject(response.data);
        }
      },
      error: function (response) {
        if (!aborted(abortToken)) {
          reject(response);
        }
      },
    });
  });
}

function save(data) {
  const abortToken = abort('save');

  return new Promise((resolve, reject) => {
    const url =
      data.id > 0
        ? AJAX_BASE + '/importer/' + data.id
        : AJAX_BASE + '/importer';
    service_xhr.save = window.jQuery.ajax({
      url: url,
      dataType: 'json',
      method: 'POST',
      data: data,
      beforeSend: function (xhr) {
        xhr.setRequestHeader('X-WP-Nonce', window.iwp.nonce);
      },
      success: function (response) {
        if (response.status === 'S') {
          importerSubject.next(response.data);
          resolve(response.data);
        } else {
          importerSubject.error(response.data);
          reject(response.data);
        }
      },
      error: function (response) {
        if (!aborted(abortToken)) {
          reject(response);
        }
      },
    });
  });
}

function pause(id, session, paused = 'yes') {
  const abortToken = abort('pause');
  return new Promise((resolve, reject) => {
    const url = AJAX_BASE + '/importer/' + id + '/pause';
    service_xhr.pause = window.jQuery.ajax({
      url: url,
      dataType: 'json',
      method: 'POST',
      data: {
        session: session,
        paused: paused,
      },
      beforeSend: function (xhr) {
        xhr.setRequestHeader('X-WP-Nonce', window.iwp.nonce);
      },
      success: function (response) {
        if (response.status === 'S') {
          resolve(response.data);
        } else {
          reject(response.data);
        }
      },
      error: function (response) {
        if (!aborted(abortToken)) {
          reject(response.statusText);
        }
      },
    });
  });
}

function stop(id, session) {
  const abortToken = abort('stop');
  return new Promise((resolve, reject) => {
    const url = AJAX_BASE + '/importer/' + id + '/stop';
    service_xhr.stop = window.jQuery.ajax({
      url: url,
      dataType: 'json',
      method: 'POST',
      data: {
        session: session,
      },
      beforeSend: function (xhr) {
        xhr.setRequestHeader('X-WP-Nonce', window.iwp.nonce);
      },
      success: function (response) {
        if (response.status === 'S') {
          resolve(response.data);
        } else {
          reject(response.data);
        }
      },
      error: function (response) {
        if (!aborted(abortToken)) {
          reject(response.statusText);
        }
      },
    });
  });
}

function remove(id) {
  const abortToken = abort('remove');
  return new Promise((resolve, reject) => {
    const url = AJAX_BASE + '/importer/' + id;
    service_xhr.remove = window.jQuery.ajax({
      url: url,
      dataType: 'json',
      method: 'POST',
      beforeSend: function (xhr) {
        xhr.setRequestHeader('X-WP-Nonce', window.iwp.nonce);
        xhr.setRequestHeader('X-HTTP-Method-Override', 'DELETE');
      },
      success: function (response) {
        if (response.status === 'S') {
          resolve(response.data);
        } else {
          reject(response.data);
        }
      },
      error: function (response) {
        if (!aborted(abortToken)) {
          reject(response.statusText);
        }
      },
    });
  });
}

function status(ids = []) {
  let abort = false;
  let xhr_requests = [];

  const newConnection = (subscriber) => {
    let jsonResponse = '',
      lastResponseLen = false;

    const xhr_request = window.jQuery.ajax({
      url: AJAX_BASE + '/status',
      dataType: 'text',
      method: 'GET',
      data: {
        ids: ids,
      },
      beforeSend: function (xhr) {
        xhr.setRequestHeader('X-WP-Nonce', window.iwp.nonce);
      },
      // xhrFields: {
      //   onprogress: function (e) {
      //     var thisResponse,
      //       response = e.currentTarget.response;
      //     if (lastResponseLen === false) {
      //       thisResponse = response;
      //       lastResponseLen = 0;
      //     } else {
      //       thisResponse = response.substring(lastResponseLen);
      //     }

      //     // TODO: Loop through response until \n, add that length onto lastResponseLen, repeat for all new line characters
      //     const split_string = '\n';
      //     while (thisResponse.includes(split_string)) {
      //       const pos = thisResponse.indexOf(split_string);
      //       const part = thisResponse.substring(0, pos);

      //       subscriber.next(JSON.parse(part));

      //       lastResponseLen += pos + split_string.length;
      //       thisResponse = thisResponse.substring(pos + 1);
      //     }
      //   },
      // },
      success: function (data) {
        try {
          const startChar = data.substring(0, 2);
          if (startChar !== '[{') {
            // we have some broken data?
            const pos = data.indexOf('[{');
            if (pos > -1) {
              data = data.substring(pos);
            }
          }

          subscriber.next(JSON.parse(data));
        } catch (e) {
          // console.log('Error Parsing data: ' + data);
          subscriber.error('Error Parsing data: ' + data);
        }
      },
      error: (e) => {
        subscriber.error(e);
      },
      complete: function () {
        if (!abort) {
          setTimeout(() => newConnection(subscriber), 5000);
        }
      },
    });

    xhr_requests.push(xhr_request);
  };

  return {
    abort: function () {
      abort = true;
      while (xhr_requests.length) {
        const xhr_request = xhr_requests.shift();
        if (xhr_request !== null) {
          xhr_request.abort();
        }
      }
    },
    request: new Observable((subscriber) => {
      abort = false;
      newConnection(subscriber);
    }),
  };
}

function importers() {
  const abortToken = abort('importers');

  return new Promise((resolve, reject) => {
    service_xhr.importers = window.jQuery.ajax({
      url: AJAX_BASE + '/importers',
      dataType: 'json',
      method: 'GET',
      beforeSend: function (xhr) {
        xhr.setRequestHeader('X-WP-Nonce', window.iwp.nonce);
      },
      success: function (response) {
        if (response.status === 'S') {
          resolve(response.data);
        } else {
          reject(response.data);
        }
      },
      error: function (response) {
        if (aborted(abortToken)) {
          return;
        }
        reject(response.statusText);
      },
    });
  });
}

function logs(id) {
  const abortToken = abort('logs');
  const url = AJAX_BASE + '/importer/' + id + '/logs';
  return new Promise((resolve, reject) => {
    service_xhr.logs = window.jQuery.ajax({
      url: url,
      dataType: 'json',
      method: 'GET',
      beforeSend: function (xhr) {
        xhr.setRequestHeader('X-WP-Nonce', window.iwp.nonce);
      },
      success: function (response) {
        if (response.status === 'S') {
          resolve(response.data);
        } else {
          reject(response.data);
        }
      },
      error: function (response) {
        if (aborted(abortToken)) {
          return;
        }

        reject(response.statusText);
      },
    });
  });
}

function log(id, session, page = 1) {
  const abortToken = abort('log');
  const url = AJAX_BASE + '/importer/' + id + '/logs/' + session;
  return new Promise((resolve, reject) => {
    service_xhr.log = window.jQuery.ajax({
      url: url,
      dataType: 'json',
      method: 'GET',
      data: {
        page: page,
      },
      beforeSend: function (xhr) {
        xhr.setRequestHeader('X-WP-Nonce', window.iwp.nonce);
      },
      success: function (response) {
        if (response.status === 'S') {
          resolve(response.data);
        } else {
          reject(response.data);
        }
      },
      error: function (response) {
        if (aborted(abortToken)) {
          return;
        }
        reject(response.statusText);
      },
    });
  });
}

function debug_log(id, page = 1) {
  const abortToken = abort('debug_log');
  const url = AJAX_BASE + '/importer/' + id + '/debug_log';
  return new Promise((resolve, reject) => {
    service_xhr.log = window.jQuery.ajax({
      url: url,
      dataType: 'json',
      method: 'GET',
      data: {
        page: page,
      },
      beforeSend: function (xhr) {
        xhr.setRequestHeader('X-WP-Nonce', window.iwp.nonce);
      },
      success: function (response) {
        if (response.status === 'S') {
          resolve(response.data);
        } else {
          reject(response.data);
        }
      },
      error: function (response) {
        if (aborted(abortToken)) {
          return;
        }
        reject(response.statusText);
      },
    });
  });
}

let fieldOptionsCache = {};

function fieldOptions(id, field, cache_key = null) {
  const abortToken = getAbortToken('fieldOptions');
  const url = AJAX_BASE + '/importer/' + id + '/field';
  return new Promise((resolve, reject) => {
    const cache = `${id}_${cache_key}`;
    if (cache_key !== null && fieldOptionsCache.hasOwnProperty(cache)) {
      if (fieldOptionsCache[cache] === null) {
        // Wait for variable to be not null
        // https://stackoverflow.com/questions/7307983/while-variable-is-not-defined-wait
        (async () => {
          // console.log("waiting for variable");
          let counter = 0;
          while (fieldOptionsCache[cache] === null && counter < 5)
            // define the condition as you like
            await new Promise((res) => {
              counter++;
              return setTimeout(res, 1000);
            });

          if (counter < 5) {
            // console.log("variable is defined");
            resolve(fieldOptionsCache[cache]);
          } else {
            reject('Timed out when fetching field options');
          }
        })();
      } else {
        resolve(fieldOptionsCache[cache]);
      }

      return;
    }

    if (cache_key !== null) {
      fieldOptionsCache[cache] = null;
    }

    service_xhr.fieldOptions = window.jQuery.ajax({
      url: url,
      dataType: 'json',
      data: {
        field: field,
      },
      method: 'POST',
      beforeSend: function (xhr) {
        xhr.setRequestHeader('X-WP-Nonce', window.iwp.nonce);
      },
      success: function (response) {
        if (response.status === 'S') {
          if (cache_key !== null) {
            fieldOptionsCache[cache] = response.data;
          }
          resolve(response.data);
        } else {
          if (cache_key !== null) {
            delete fieldOptionsCache[cache];
          }
          reject(response.data);
        }
      },
      error: function (response) {
        if (cache_key !== null) {
          delete fieldOptionsCache[cache];
        }

        if (aborted(abortToken)) {
          return;
        }
        reject(response.statusText);
      },
    });
  });
}

function check() {
  const abortToken = abort('check');
  const url = AJAX_BASE + '/system/check';
  return new Promise((resolve, reject) => {
    service_xhr.check = window.jQuery.ajax({
      url: url,
      dataType: 'json',
      beforeSend: function (xhr) {
        xhr.setRequestHeader('X-WP-Nonce', window.iwp.nonce);
      },
      success: function (response) {
        if (response.status === 'S') {
          resolve(response.data);
        } else {
          reject(response.data);
        }
      },
      error: function (response) {
        if (!aborted(abortToken)) {
          reject(response.statusText);
        }
      },
    });
  });
}

function migrate() {
  const abortToken = abort('migrate');

  const url = AJAX_BASE + '/system/migrate';
  return new Promise((resolve, reject) => {
    service_xhr.migrate = window.jQuery.ajax({
      url: url,
      dataType: 'json',
      beforeSend: function (xhr) {
        xhr.setRequestHeader('X-WP-Nonce', window.iwp.nonce);
      },
      success: function (response) {
        if (response.status === 'S') {
          resolve(response.data);
        } else {
          reject(response.data);
        }
      },
      error: function (response) {
        if (!aborted(abortToken)) {
          reject(response.statusText);
        }
      },
    });
  });
}

function getSettings() {
  const abortToken = abort('getSettings');
  new Promise((resolve, reject) => {
    service_xhr.getSettings = window.jQuery.ajax({
      url: AJAX_BASE + '/settings',
      dataType: 'json',
      method: 'GET',
      beforeSend: function (xhr) {
        xhr.setRequestHeader('X-WP-Nonce', window.iwp.nonce);
      },
      success: function (response) {
        if (response.status === 'S') {
          settingsSubject.next(response.data);
          resolve(response.data);
        } else {
          settingsSubject.error(response.data);
          reject(response.data);
        }
      },
      error: function (response) {
        if (!aborted(abortToken)) {
          settingsSubject.error(response);
          reject(response.statusText);
        }
      },
    });
  });

  return settingsSubject;
}

function saveSettings(data) {
  const abortToken = abort('saveSettings');

  return new Promise((resolve, reject) => {
    const url = AJAX_BASE + '/settings';
    service_xhr.saveSettings = window.jQuery.ajax({
      url: url,
      dataType: 'json',
      method: 'POST',
      data: data,
      beforeSend: function (xhr) {
        xhr.setRequestHeader('X-WP-Nonce', window.iwp.nonce);
      },
      success: function (response) {
        if (response.status === 'S') {
          settingsSubject.next(response.data);
          resolve(response.data);
        } else {
          settingsSubject.error(response.data);
          reject(response.data);
        }
      },
      error: function (response) {
        if (aborted(abortToken)) {
          return;
        }
        settingsSubject.error(response);
        reject(response.statusText);
      },
    });
  });
}

function getCompatibility() {
  const abortToken = abort('getCompatibility');
  new Promise((resolve, reject) => {
    service_xhr.getCompatibility = window.jQuery.ajax({
      url: AJAX_BASE + '/compatibility',
      dataType: 'json',
      method: 'GET',
      beforeSend: function (xhr) {
        xhr.setRequestHeader('X-WP-Nonce', window.iwp.nonce);
      },
      success: function (response) {
        if (response.status === 'S') {
          compatibilitySubject.next(response.data);
          resolve(response.data);
        } else {
          compatibilitySubject.error(response.data);
          reject(response.data);
        }
      },
      error: function (response) {
        if (!aborted(abortToken)) {
          compatibilitySubject.error(response);
          reject(response.statusText);
        }
      },
    });
  });

  return compatibilitySubject;
}

function saveCompatibility(data) {
  const abortToken = abort('saveCompatibility');

  return new Promise((resolve, reject) => {
    const url = AJAX_BASE + '/compatibility';
    service_xhr.saveCompatibility = window.jQuery.ajax({
      url: url,
      dataType: 'json',
      method: 'POST',
      data: data,
      beforeSend: function (xhr) {
        xhr.setRequestHeader('X-WP-Nonce', window.iwp.nonce);
      },
      success: function (response) {
        if (response.status === 'S') {
          compatibilitySubject.next(response.data);
          resolve(response.data);
        } else {
          compatibilitySubject.error(response.data);
          reject(response.data);
        }
      },
      error: function (response) {
        if (aborted(abortToken)) {
          return;
        }
        compatibilitySubject.error(response);
        reject(response.statusText);
      },
    });
  });
}

function template(id) {
  const abortToken = getAbortToken('template');
  const url = AJAX_BASE + '/importer/' + id + '/template';
  return new Promise((resolve, reject) => {
    service_xhr.template = window.jQuery.ajax({
      url: url,
      dataType: 'json',
      method: 'GET',
      beforeSend: function (xhr) {
        xhr.setRequestHeader('X-WP-Nonce', window.iwp.nonce);
      },
      success: function (response) {
        if (response.status === 'S') {
          resolve(response.data);
        } else {
          reject(response.data);
        }
      },
      error: function (response) {
        if (aborted(abortToken)) {
          return;
        }
        reject(response.statusText);
      },
    });
  });
}

function templateUniqueIdentifiers(id) {
  const abortToken = getAbortToken('templateUniqueIdentifiers');
  const url = AJAX_BASE + '/importer/' + id + '/template_unique_identifiers';
  return new Promise((resolve, reject) => {
    service_xhr.template = window.jQuery.ajax({
      url: url,
      dataType: 'json',
      method: 'GET',
      beforeSend: function (xhr) {
        xhr.setRequestHeader('X-WP-Nonce', window.iwp.nonce);
      },
      success: function (response) {
        if (response.status === 'S') {
          resolve(response.data);
        } else {
          reject(response.data);
        }
      },
      error: function (response) {
        if (aborted(abortToken)) {
          return;
        }
        reject(response.statusText);
      },
    });
  });
}

function toolExport(ids) {
  const sep = AJAX_BASE.includes('?') ? '&' : '?';
  const url =
    AJAX_BASE +
    '/import-export' +
    sep +
    'ids%5B%5D=' +
    ids.join('&ids%5B%5D=') +
    '&_wpnonce=' +
    window.iwp.nonce;

  let a = document.createElement('a');
  a.href = url;
  a.download = url.split('/').pop();
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
}

function toolImport(form_data) {
  const abortToken = abort('upload');

  return new Promise((resolve, reject) => {
    const url = AJAX_BASE + '/import-export';
    service_xhr.upload = window.jQuery.ajax({
      url: url,
      dataType: 'json',
      contentType: false,
      processData: false,
      method: 'POST',
      data: form_data,
      beforeSend: function (xhr) {
        xhr.setRequestHeader('X-WP-Nonce', window.iwp.nonce);
      },
      success: function (response) {
        if (response.status === 'S') {
          importerSubject.next(response.data);
          resolve(response.data);
        } else {
          reject(response.data);
        }
      },
      error: function (response) {
        if (!aborted(abortToken)) {
          importerSubject.error(response);
          reject(response.statusText);
        }
      },
    });
  });
}
