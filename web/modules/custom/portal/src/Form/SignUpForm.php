<?php

namespace Drupal\portal\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailManager;
use Drupal\Core\Messenger\Messenger;
use Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Consists of the SignUp Form for the student.
 */
class SignUpForm extends FormBase {

  /**
   * Creates connection with the database.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Displays messages if any database error occurs.
   *
   * @var \Drupal\Core\Messenger\Messenger
   */
  protected $message;

  /**
   * Displays messages if any database error occurs.
   *
   * @var \Drupal\Core\Mail\MailManager
   */
  protected $mail;

  /**
   * Constructs a new SearchForm object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(Connection $database, Messenger $messenger, MailManager $mailManager) {
    $this->connection = $database;
    $this->message = $messenger;
    $this->mail = $mailManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('messenger'),
      $container->get('plugin.manager.mail'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'search_taxonomy';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['full_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Full Name'),
      '#required' => TRUE,
    ];

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email Address'),
      '#required' => TRUE,
    ];

    $form['passcode'] = [
      '#type' => 'password',
      '#title' => $this->t('Password'),
      '#required' => TRUE,
    ];

    $form['contact'] = [
      '#type' => 'tel',
      '#title' => $this->t('Phone Number'),
      '#required' => TRUE,
    ];

    $form['stream'] = [
      '#type' => 'select',
      '#options' => [
        'CSE' => $this->t('CSE'),
        'CE' => $this->t('CE'),
        'ME' => $this->t('ME'),
        'IT' => $this->t('IT'),
        'ECE' => $this->t('ECE'),
      ],
      '#title' => $this->t('Select Your Stream'),
      '#required' => TRUE,
    ];

    $form['jyear'] = [
      '#type' => 'date',
      '#title' => $this->t('Date of Joining'),
      '#required' => TRUE,
    ];

    $form['pyear'] = [
      '#type' => 'date',
      '#title' => $this->t('Date of Passing'),
      '#required' => TRUE,
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    // Checks if the phone number is 10 digit number.
    if (!preg_match('/\d{10}$/', $form_state->getValue('contact'))) {
      $form_state->setErrorByName('contact', $this->t('Phone number of 10 digits is accepted'));
    }

    // Validates the email format, the domain and ends with '.com'.
    if (!(filter_var($form_state->getValue('email'), FILTER_VALIDATE_EMAIL))) {
      $form_state->setErrorByName('email', $this->t('Invalid email address.'));
    }
  }


  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    try{
      $field = $form_state->getValues();
       
      $fields['full_name'] = $field['full_name'];
      $fields['email'] = $field['email'];
      $fields['passcode'] = $field['passcode'];
      $fields['contact'] = $field['contact'];
      $fields['stream'] = $field['stream'];
      $fields['jyear'] = $field['jyear'];
      $fields['pyear'] = $field['pyear'];

      $this->connection->insert('students')
           ->fields($fields)->execute();

      $params = [
        'values' => $fields,
        'id' => $this->getId($fields['email']),
      ];
      $this->mail->mail('portal', 'portal_submit', $fields['email'], 'en', $params);
      $this->mail->mail('portal', 'portal_submit', $this->getMail(), 'en', $params);

      $this->message->addMessage('Form Submitted');
    } catch(Exception $e){
      $this->message->addMessage($e);
    }
  }

  /**
   * Gets the id assigned to the user
   *
   * @param string $email
   *   The mail address entered by the user.
   *
   * @return int
   *   The id of the student.
   */
  public function getId($email) {
    $query = $this->connection->select('students', 's');
    $query->fields('s', ['id']);
    $query->condition('s.email', $email, '=');
    $result = $query->execute()->fetchAll();

    foreach ($result as $record) {
      return $record->id;
    }
  }

  /**
   * Gets mail address of the admin.
   *
   * @return string
   *   The mail of the admin.
   */
  public function getMail() {
    $query = $this->connection->select('user_field_data', 'ufd');
    $query->fields('ufd', ['mail']);
    $query->condition('ufd.uid', 1, '=');
    $result = $query->execute()->fetchAll();

    foreach ($result as $record) {
      return $record->mail;
    }
  }
}
