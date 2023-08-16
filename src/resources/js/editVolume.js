$(document).ready(function () {
  const $ossAccessKeyId = $('.oss-access-key-id');
  const $ossAccessKeySecretId = $('.oss-access-key-secret-id');
  const $ossBucketSelect = $('.oss-bucket-select > select');
  const $ossRefreshBucketsBtn = $('.oss-refresh-buckets');
  const $ossRefreshBucketsSpinner = $ossRefreshBucketsBtn
    .parent()
    .next()
    .children();
  const $ossRegion = $('.oss-region');
  const $manualBucket = $('.oss-manualBucket');
  const $manualRegion = $('.oss-manualRegion');
  const $fsUrl = $('.fs-url');
  const $hasUrls = $('input[name=hasUrls]');
  let refreshingS3Buckets = false;

  $ossRefreshBucketsBtn.click(function () {
    if ($ossRefreshBucketsBtn.hasClass('disabled')) {
      return;
    }

    $ossRefreshBucketsBtn.addClass('disabled');
    $ossRefreshBucketsSpinner.removeClass('hidden');

    const data = {
      keyId: $ossAccessKeyId.val(),
      secret: $ossAccessKeySecretId.val(),
    };

    Craft.sendActionRequest('POST', 'alibaba-oss/buckets/load-bucket-data', {data})
      .then(({data}) => {
        if (!data.buckets.length) {
          return;
        }
        //
        const currentBucket = $ossBucketSelect.val();
        let currentBucketStillExists = false;

        refreshingS3Buckets = true;

        $ossBucketSelect.prop('readonly', false).empty();

        for (let i = 0; i < data.buckets.length; i++) {
          if (data.buckets[i].bucket == currentBucket) {
            currentBucketStillExists = true;
          }

          $ossBucketSelect.append(
            '<option value="' +
              data.buckets[i].bucket +
              '" data-url-prefix="' +
              data.buckets[i].urlPrefix +
              '" data-region="' +
              data.buckets[i].region +
              '">' +
              data.buckets[i].bucket +
              '</option>'
          );
        }

        if (currentBucketStillExists) {
          $ossBucketSelect.val(currentBucket);
        }

        refreshingS3Buckets = false;

        if (!currentBucketStillExists) {
          $ossBucketSelect.trigger('change');
        }
      })
      .catch(({response}) => {
        alert(response.data.message);
      })
      .finally(() => {
        $ossRefreshBucketsBtn.removeClass('disabled');
        $ossRefreshBucketsSpinner.addClass('hidden');
      });
  });

  $ossBucketSelect.change(function () {
    if (refreshingS3Buckets) {
      return;
    }

    const $selectedOption = $ossBucketSelect.children('option:selected');

    $fsUrl.val($selectedOption.data('url-prefix'));
    $ossRegion.val($selectedOption.data('region'));
  });

  const ossChangeExpiryValue = function () {
    const parent = $(this).parents('.field');
    const amount = parent.find('.oss-expires-amount').val();
    const period = parent.find('.oss-expires-period select').val();

    const combinedValue =
      parseInt(amount, 10) === 0 || period.length === 0
        ? ''
        : amount + ' ' + period;

    parent.find('[type=hidden]').val(combinedValue);
  };

  $('.oss-expires-amount')
    .keyup(ossChangeExpiryValue)
    .change(ossChangeExpiryValue);
  $('.oss-expires-period select').change(ossChangeExpiryValue);

  const maybeUpdateUrl = function () {
    if (
      $hasUrls.val() &&
      $manualBucket.val().length &&
      $manualRegion.val().length
    ) {
      $fsUrl.val(
        'https://.' +
          $manualBucket.val() +
          '.' +
          $manualRegion.val() +
          '.aliyuncs.com/'
      );
    }
  };

  $manualRegion.keyup(maybeUpdateUrl);
  $manualBucket.keyup(maybeUpdateUrl);
});
