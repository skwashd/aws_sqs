<?php

namespace Drupal\aws_sqs\Model;

/**
 * Queue item model.
 */
class QueueItem {

  /**
   * Arbitrary data associated with a queue task.
   *
   * @var mixed
   */
  protected $data;

  /**
   * Queue item id.
   *
   * @var string
   */
  protected $itemId;

  /**
   * Receipt handle.
   *
   * @var string
   */
  protected $receiptHandle;

  /**
   * Gets data.
   *
   * @return mixed
   *   The data.
   */
  public function getData() {
    return $this->data;
  }

  /**
   * Sets data.
   *
   * @param mixed $data
   *   The data.
   */
  public function setData($data) {
    $this->data = $data;
  }

  /**
   * Gets id.
   *
   * @return string
   *   The id.
   */
  public function getItemId() {
    return $this->itemId;
  }

  /**
   * Sets id.
   *
   * @param string $itemId
   *   The id.
   */
  public function setItemId($itemId) {
    $this->itemId = $itemId;
  }

  /**
   * Gets receipt handle.
   *
   * @return string
   *   The receipt handle.
   */
  public function getReceiptHandle() {
    return $this->itemId;
  }

  /**
   * Sets receipt handle.
   *
   * @param string $receipt_handle
   *   The receipt handle.
   */
  public function setReceiptHandle($receipt_handle) {
    $this->receiptHandle = $receipt_handle;
  }

}
