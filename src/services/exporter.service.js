import { Observable, Subject } from 'rxjs';

const AJAX_BASE = window.iwp.ajax_base;
let service_xhr = {};

const exporterSubject = new Subject(null);

export const exporter = {
  get,
  getAndSubscribe,
  save,
  exporters,
  remove,
  init,
  run,
  status,
  abort,
  exportConfig
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

function getAndSubscribe(id) {
  get(id);
  return exporterSubject;
}

function get(id) {
  const abortToken = abort('get');

  return new Promise((resolve, reject) => {
    service_xhr.get = window.jQuery.ajax({
      url: AJAX_BASE + '/exporter/' + id,
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
          exporterSubject.next(response.data);
          resolve(response.data);
        } else {
          exporterSubject.error(response.data);
          reject(response.data);
        }
      },
      error: function (response) {
        // if aborted skip
        if (aborted(abortToken)) {
          return;
        }

        exporterSubject.error(response);
        reject(response.statusText);
      },
    });
  });
}

function save(data) {
  const abortToken = abort('save');

  return new Promise((resolve, reject) => {
    const url =
      data.id > 0
        ? AJAX_BASE + '/exporter/' + data.id
        : AJAX_BASE + '/exporter';
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
          exporterSubject.next(response.data);
          resolve(response.data);
        } else {
          exporterSubject.error(response.data);
          reject(response.data);
        }
      },
      error: function (response) {
        if (!aborted(abortToken)) {
          exporterSubject.error(response);
          reject(response.statusText);
        }
      },
    });
  });
}

function exporters() {
  const abortToken = abort('exporters');

  return new Promise((resolve, reject) => {
    service_xhr.exporters = window.jQuery.ajax({
      url: AJAX_BASE + '/exporters',
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

function remove(id) {
  const abortToken = abort('remove');
  return new Promise((resolve, reject) => {
    const url = AJAX_BASE + '/exporter/' + id;
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

function init(id) {
  const abortToken = abort('init');

  return new Promise((resolve, reject) => {
    service_xhr.init = window.jQuery.ajax({
      url: AJAX_BASE + '/exporter/' + id + '/init',
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

function run(id, session = '') {
  let abort = false;
  let xhr_requests = [];

  const newConnection = (subscriber) => {
    const xhr_request = window.jQuery.ajax({
      url: AJAX_BASE + '/exporter/' + id + '/run',
      dataType: 'text',
      method: 'POST',
      data: {
        session: session,
      },
      beforeSend: function (xhr) {
        xhr.setRequestHeader('X-WP-Nonce', window.iwp.nonce);
      },
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

function status(ids = []) {
  let abort = false;
  let xhr_requests = [];

  const newConnection = (subscriber) => {
    const xhr_request = window.jQuery.ajax({
      url: AJAX_BASE + '/exporter/status',
      dataType: 'text',
      method: 'GET',
      data: {
        ids: ids,
      },
      beforeSend: function (xhr) {
        xhr.setRequestHeader('X-WP-Nonce', window.iwp.nonce);
      },
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

function exportConfig(id) {
  const sep = AJAX_BASE.includes('?') ? '&' : '?';
  const url = AJAX_BASE + '/exporter/' + id + '/download-config' + sep + '_wpnonce=' +
    window.iwp.nonce;

  let a = document.createElement('a');
  a.href = url;
  a.download = url.split('/').pop();
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
}